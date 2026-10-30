import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'dart:convert';
import '../services/api_service.dart';
import '../services/offline_database_service.dart';
import '../services/sync_service.dart';

class HistoryScreen extends StatefulWidget {
  final String? initialFilter;
  const HistoryScreen({super.key, this.initialFilter});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  List<dynamic> _orders = [];
  List<Map<String, dynamic>> _offlineOrders = [];
  bool _loading = true;
  late String _filter;
  final TextEditingController _searchCtrl = TextEditingController();
  String _searchQuery = '';
  DateTimeRange? _selectedDateRange;
  int? _syncingOrderId;

  @override
  void initState() {
    super.initState();
    _filter = widget.initialFilter ?? 'Todos';
    _searchCtrl.addListener(() {
      setState(() {
        _searchQuery = _searchCtrl.text;
      });
    });
    _loadOrders();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        context.read<SyncService>().addListener(_onSyncServiceUpdated);
      }
    });
  }

  void _onSyncServiceUpdated() {
    if (!mounted) return;
    final syncService = context.read<SyncService>();
    if (!syncService.isSyncing) {
      _loadOrders();
    }
  }

  @override
  void dispose() {
    try {
      context.read<SyncService>().removeListener(_onSyncServiceUpdated);
    } catch (_) {}
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadOrders() async {
    setState(() => _loading = true);
    final api = context.read<ApiService>();
    final syncService = context.read<SyncService>();

    try {
      final offline = await OfflineDatabaseService.instance.getUnsyncedOrders();
      _offlineOrders = offline;
    } catch (e) {
      debugPrint('Error cargando pedidos offline: $e');
    }

    try {
      final data = await api.get('/orders');
      if (mounted && data is List) {
        // Reconciliar pedidos locales que ya fueron sincronizados o existen en el servidor
        await syncService.reconcileWithRemoteOrders(data);
        final updatedOffline = await OfflineDatabaseService.instance.getUnsyncedOrders();
        if (mounted) {
          setState(() {
            _orders = data;
            _offlineOrders = updatedOffline;
          });
        }
      }
    } catch (e) {
      debugPrint('Error cargando pedidos remotos: $e');
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _syncSingleOrder(dynamic order) async {
    final int? localId = order['local_id'];
    if (localId == null) return;

    final syncService = context.read<SyncService>();
    final api = context.read<ApiService>();

    if (!syncService.isOnline) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No tienes conexión a internet para sincronizar este pedido.'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    setState(() => _syncingOrderId = localId);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Sincronizando pedido local #LOCAL-$localId...'),
        duration: const Duration(seconds: 2),
      ),
    );

    try {
      final success = await syncService.syncSingleOrder(localId, api);
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Pedido #LOCAL-$localId sincronizado con éxito.'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error al sincronizar #LOCAL-$localId: $e'),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 4),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _syncingOrderId = null);
        await _loadOrders();
      }
    }
  }

  Future<void> _confirmMarkAsInvoiced(dynamic order) async {
    final localId = order['local_id'];
    if (localId == null) return;

    final clientName = order['client']?['nombre_local'] ??
        order['client']?['company_name'] ??
        order['client']?['name'] ??
        'Cliente';

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: Colors.green),
            SizedBox(width: 8),
            Text('¿Marcar como facturada?'),
          ],
        ),
        content: Text(
          'El pedido local #LOCAL-$localId de "$clientName" se marcará como ya facturado y se quitará de la cola de sincronización.\n\n'
          '⚠️ Utiliza esta opción si el pedido ya fue registrado o facturado en el sistema para evitar facturas duplicadas.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
            icon: const Icon(Icons.check, size: 16),
            onPressed: () => Navigator.pop(ctx, true),
            label: const Text('Sí, marcar facturada'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await OfflineDatabaseService.instance.markOrderSynced(localId);
      if (mounted) {
        context.read<SyncService>().checkPendingCounts();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Pedido #LOCAL-$localId marcado como ya facturado.'),
            backgroundColor: Colors.green,
          ),
        );
        await _loadOrders();
      }
    }
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null) return 'N/A';
    try {
      final dt = DateTime.parse(dateStr);
      return '${dt.day.toString().padLeft(2, '0')}/${dt.month.toString().padLeft(2, '0')}/${dt.year}';
    } catch (_) {
      return dateStr.split('T')[0];
    }
  }

  String _formatDateShort(DateTime dt) {
    return '${dt.day}/${dt.month}';
  }

  Future<void> _selectDateRange() async {
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      initialDateRange: _selectedDateRange ?? DateTimeRange(
        start: DateTime.now().subtract(const Duration(days: 7)),
        end: DateTime.now(),
      ),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF03A9F4),
              onPrimary: Colors.white,
              onSurface: Colors.black87,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() => _selectedDateRange = picked);
    }
  }

  Future<void> _generatePdfReport() async {
    final api = context.read<ApiService>();
    final token = api.token;
    final baseUrl = ApiService.baseUrl;
    
    final uriStr = '$baseUrl/orders/report-pdf?token=$token'
        '&start_date=${_selectedDateRange != null ? _selectedDateRange!.start.toIso8601String().split('T')[0] : ""}'
        '&end_date=${_selectedDateRange != null ? _selectedDateRange!.end.toIso8601String().split('T')[0] : ""}'
        '&status=$_filter';
        
    final uri = Uri.parse(uriStr);
    
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo abrir el PDF del reporte'), backgroundColor: Colors.red),
        );
      }
    }
  }

  List<dynamic> get _filteredOrders {
    // Formatear pedidos offline
    final List<dynamic> offlineFormatted = _offlineOrders.map((o) {
      Map<String, dynamic> payload = {};
      try {
        payload = jsonDecode(o['payload_json'] ?? '{}');
      } catch (_) {}
      return {
        'id': 'LOCAL-${o['id']}',
        'local_id': o['id'],
        'is_offline': true,
        'client_id': o['client_id'],
        'client': {
          'id': o['client_id'],
          'name': o['client_name'] ?? 'Cliente',
        },
        'total': o['total'],
        'status': 'offline_pending',
        'is_invoiced': payload['is_direct_invoice'] == true,
        'is_preinvoiced': payload['is_direct_invoice'] != true,
        'delivery_date': payload['delivery_date'],
        'created_at': o['created_at'],
        'sync_error': o['sync_error'],
      };
    }).toList();

    List<dynamic> list = [...offlineFormatted, ..._orders];

    if (_filter == 'Pendientes') {
      list = list
          .where((o) => o['status'] == 'pending' || o['status'] == 'processed' || o['is_offline'] == true)
          .toList();
    } else if (_filter == 'Completados') {
      list = list.where((o) => o['status'] == 'delivered').toList();
    } else if (_filter == 'Cancelados') {
      list = list.where((o) => o['status'] == 'cancelled').toList();
    } else if (_filter == 'Entregas Hoy') {
      final now = DateTime.now();
      list = list.where((o) {
        if (o['delivery_date'] == null) return false;
        try {
          final d = DateTime.parse(o['delivery_date']);
          return d.year == now.year &&
              d.month == now.month &&
              d.day == now.day &&
              o['status'] != 'cancelled';
        } catch (_) {
          return false;
        }
      }).toList();
    }

    if (_searchQuery.isNotEmpty) {
      final q = _searchQuery.toLowerCase();
      list = list.where((o) {
        final clientName = (o['client']?['nombre_local'] ?? o['client']?['name'] ?? '').toString().toLowerCase();
        final orderId = o['id'].toString();
        return clientName.contains(q) || orderId.contains(q);
      }).toList();
    }

    if (_selectedDateRange != null) {
      list = list.where((o) {
        final dateStr = o['created_at'] ?? o['delivery_date'];
        if (dateStr == null) return false;
        try {
          final dt = DateTime.parse(dateStr);
          final start = DateTime(_selectedDateRange!.start.year, _selectedDateRange!.start.month, _selectedDateRange!.start.day);
          final end = DateTime(_selectedDateRange!.end.year, _selectedDateRange!.end.month, _selectedDateRange!.end.day, 23, 59, 59);
          return dt.isAfter(start.subtract(const Duration(seconds: 1))) && dt.isBefore(end.add(const Duration(seconds: 1)));
        } catch (_) {
          return false;
        }
      }).toList();
    }

    return list;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Historial de Pedidos',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.picture_as_pdf, color: Colors.red),
            tooltip: 'Generar Reporte PDF',
            onPressed: _generatePdfReport,
          ),
          const SizedBox(width: 10),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            child: TextField(
              controller: _searchCtrl,
              decoration: InputDecoration(
                hintText: 'Buscar por cliente o ID...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (_selectedDateRange != null) ...[
                      Text(
                        '${_formatDateShort(_selectedDateRange!.start)} - ${_formatDateShort(_selectedDateRange!.end)}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF03A9F4),
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close, color: Colors.red, size: 20),
                        onPressed: () => setState(() => _selectedDateRange = null),
                      ),
                    ] else
                      IconButton(
                        icon: const Icon(Icons.calendar_today, color: Color(0xFF03A9F4), size: 20),
                        onPressed: _selectDateRange,
                      ),
                  ],
                ),
                fillColor: const Color(0xFFF1FBFE),
                filled: true,
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none),
              ),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: Row(
              children: [
                _buildFilterChip('Todos'),
                _buildFilterChip('Entregas Hoy'),
                _buildFilterChip('Pendientes'),
                _buildFilterChip('Completados'),
                _buildFilterChip('Cancelados'),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _filteredOrders.isEmpty
                    ? const Center(child: Text('No hay pedidos que mostrar'))
                    : RefreshIndicator(
                        onRefresh: _loadOrders,
                        child: ListView.builder(
                          padding: const EdgeInsets.all(20),
                          itemCount: _filteredOrders.length,
                          itemBuilder: (context, index) {
                            final order = _filteredOrders[index];
                            return _buildOrderCard(order);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label) {
    bool isSelected = _filter == label;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 5),
      child: ChoiceChip(
        label: Text(label),
        selected: isSelected,
        onSelected: (val) => setState(() => _filter = label),
        selectedColor: const Color(0xFFE1F5FE),
        backgroundColor: Colors.white,
        labelStyle: TextStyle(
            color: isSelected ? const Color(0xFF03A9F4) : Colors.grey),
        side: BorderSide(
            color: isSelected ? const Color(0xFF03A9F4) : Colors.grey[200]!),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
    );
  }

  Widget _buildOrderCard(dynamic order) {
    final bool isOffline = order['is_offline'] == true;
    String status = order['status'] ?? 'pending';
    String statusLabel = 'En proceso';
    Color statusColor = Colors.orange;

    if (isOffline) {
      final hasError = order['sync_error'] != null;
      statusLabel = hasError ? 'Error de Envío' : 'Por Sincronizar';
      statusColor = hasError ? Colors.red : Colors.orange;
    } else if (status == 'cancelled') {
      statusLabel = 'Anulado';
      statusColor = Colors.red;
    } else if (status == 'delivered') {
      statusLabel = 'Entregado';
      statusColor = Colors.green;
    } else if (status == 'invoiced' || (order['is_invoiced'] == true || order['is_invoiced'] == 1)) {
      statusLabel = 'Facturado';
      statusColor = Colors.blue;
    } else if (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) {
      statusLabel = 'Prefacturado';
      statusColor = Colors.deepPurple;
    }

    if (!isOffline && status != 'delivered' && status != 'cancelled') {
      final deliveryDateStr = order['delivery_date'];
      if (deliveryDateStr != null) {
        try {
          final deliveryDate = DateTime.parse(deliveryDateStr);
          final now = DateTime.now();
          if (deliveryDate.year == now.year &&
              deliveryDate.month == now.month &&
              deliveryDate.day == now.day) {
            statusLabel = 'En camino';
            statusColor = Colors.purple;
          }
        } catch (_) {}
      }
    }

    return GestureDetector(
      onTap: () {
        if (isOffline) {
          showDialog(
            context: context,
            builder: (ctx) => AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  Icon(
                    order['sync_error'] != null ? Icons.warning_amber_rounded : Icons.offline_pin,
                    color: order['sync_error'] != null ? Colors.red : Colors.orange,
                  ),
                  const SizedBox(width: 8),
                  const Text('Pedido Local (Offline)'),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Cliente: ${order['client']?['name'] ?? "N/A"}', style: const TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  Text('Total: \$${double.tryParse(order['total']?.toString() ?? '0.0')?.toStringAsFixed(2) ?? '0.00'}'),
                  Text('Fecha de registro: ${_formatDate(order['created_at'])}'),
                  if (order['delivery_date'] != null)
                    Text('Fecha de entrega: ${_formatDate(order['delivery_date'])}'),
                  if (order['sync_error'] != null) ...[
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Text(
                        'Motivo error: ${order['sync_error']}',
                        style: TextStyle(color: Colors.red.shade900, fontSize: 12),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.blue.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.blue.shade200),
                    ),
                    child: const Text(
                      'Si este pedido ya fue facturado en el sistema o es antiguo, márcalo como ya facturado para que no se duplique al sincronizar.',
                      style: TextStyle(fontSize: 12, color: Colors.blueGrey),
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: const Text('Cerrar'),
                ),
                OutlinedButton.icon(
                  onPressed: () {
                    Navigator.pop(ctx);
                    _confirmMarkAsInvoiced(order);
                  },
                  icon: const Icon(Icons.check_circle_outline, size: 16, color: Colors.green),
                  label: const Text('Marcar Ya Facturada', style: TextStyle(color: Colors.green)),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Colors.green),
                  ),
                ),
                ElevatedButton.icon(
                  onPressed: () {
                    Navigator.pop(ctx);
                    _syncSingleOrder(order);
                  },
                  icon: const Icon(Icons.sync, size: 16),
                  label: const Text('Sincronizar este pedido'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF03A9F4),
                    foregroundColor: Colors.white,
                  ),
                ),
              ],
            ),
          );
        } else {
          Navigator.pushNamed(context, '/order_detail', arguments: order['id']).then((_) => _loadOrders());
        }
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 15),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isOffline ? Colors.amber.shade50.withValues(alpha: 0.3) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: isOffline ? Colors.amber.shade300 : Colors.grey[100]!),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(isOffline ? 'Pedido Local #${order['local_id']}' : 'Pedido #${order['id']}',
                              style: const TextStyle(
                                  fontWeight: FontWeight.bold, fontSize: 16)),
                          if (isOffline) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: Colors.amber.shade100,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: const Text(
                                'OFFLINE',
                                style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.brown),
                              ),
                            ),
                          ],
                        ],
                      ),
                      Text('Cliente: ${(order['client']?['nombre_local'] ?? order['client']?['company_name'] ?? order['client']?['name'] ?? 'N/A')}',
                          style: const TextStyle(color: Colors.grey),
                          overflow: TextOverflow.ellipsis,
                          maxLines: 1),
                      const SizedBox(height: 4),
                      Text('Fecha: ${_formatDate(order['created_at'] ?? order['delivery_date'])}',
                          style: const TextStyle(color: Colors.grey, fontSize: 12)),
                      const SizedBox(height: 2),
                      Text('Total: \$${double.tryParse(order['total']?.toString() ?? '0.0')?.toStringAsFixed(2) ?? '0.00'}',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF03A9F4), fontSize: 13)),
                      if ((order['is_invoiced'] == true || order['is_invoiced'] == 1) || (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1))
                        Padding(
                          padding: const EdgeInsets.only(top: 4.0),
                          child: Row(
                            children: [
                              Icon(Icons.receipt_long,
                                  size: 14, color: (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? Colors.purple : Colors.blue),
                              const SizedBox(width: 4),
                              Text(
                                  (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? 'Prefacturado' : 'Facturado',
                                  style: TextStyle(
                                      fontSize: 12,
                                      color: (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? Colors.purple : Colors.blue,
                                      fontWeight: FontWeight.bold)),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(statusLabel,
                      style: TextStyle(
                          color: statusColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 12)),
                ),
              ],
            ),
            if (isOffline) ...[
              const SizedBox(height: 10),
              const Divider(height: 1),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  OutlinedButton.icon(
                    onPressed: _syncingOrderId != null ? null : () => _confirmMarkAsInvoiced(order),
                    icon: const Icon(Icons.check_circle_outline, size: 14, color: Colors.green),
                    label: const Text(
                      'Marcar ya facturada',
                      style: TextStyle(color: Colors.green, fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      side: BorderSide(color: Colors.green.shade400),
                      backgroundColor: Colors.green.shade50,
                      visualDensity: VisualDensity.compact,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                  ElevatedButton.icon(
                    onPressed: _syncingOrderId != null ? null : () => _syncSingleOrder(order),
                    icon: _syncingOrderId == order['local_id']
                        ? const SizedBox(
                            width: 13,
                            height: 13,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.sync, size: 15),
                    label: Text(
                      _syncingOrderId == order['local_id'] ? 'Sincronizando...' : 'Sincronizar',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF03A9F4),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      visualDensity: VisualDensity.compact,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
