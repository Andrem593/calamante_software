import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/api_service.dart';

class HistoryScreen extends StatefulWidget {
  final String? initialFilter;
  const HistoryScreen({super.key, this.initialFilter});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  List<dynamic> _orders = [];
  bool _loading = true;
  late String _filter;
  final TextEditingController _searchCtrl = TextEditingController();
  String _searchQuery = '';
  DateTimeRange? _selectedDateRange;

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
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadOrders() async {
    setState(() => _loading = true);
    try {
      final data = await context.read<ApiService>().get('/orders');
      setState(() => _orders = data as List);
    } catch (e) {
      print(e);
    } finally {
      setState(() => _loading = false);
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo abrir el PDF del reporte'), backgroundColor: Colors.red),
      );
    }
  }

  List<dynamic> get _filteredOrders {
    List<dynamic> list = _orders;
    if (_filter == 'Pendientes') {
      list = list
          .where((o) => o['status'] == 'pending' || o['status'] == 'processed')
          .toList();
    } else if (_filter == 'Completados') {
      list = list.where((o) => o['status'] == 'delivered').toList();
    } else if (_filter == 'Cancelados') {
      list = list.where((o) => o['status'] == 'cancelled').toList();
    } else if (_filter == 'Entregas Hoy') {
      final now = DateTime.now();
      list = list.where((o) {
        if (o['delivery_date'] == null) return false;
        final d = DateTime.parse(o['delivery_date']);
        return d.year == now.year &&
            d.month == now.month &&
            d.day == now.day &&
            o['status'] != 'cancelled';
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
        final dt = DateTime.parse(dateStr);
        final start = DateTime(_selectedDateRange!.start.year, _selectedDateRange!.start.month, _selectedDateRange!.start.day);
        final end = DateTime(_selectedDateRange!.end.year, _selectedDateRange!.end.month, _selectedDateRange!.end.day, 23, 59, 59);
        return dt.isAfter(start.subtract(const Duration(seconds: 1))) && dt.isBefore(end.add(const Duration(seconds: 1)));
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
    String status = order['status'] ?? 'pending';
    String statusLabel = 'En proceso';
    Color statusColor = Colors.orange;

    if (status == 'cancelled') {
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

    if (status != 'delivered' && status != 'cancelled') {
      final deliveryDateStr = order['delivery_date'];
      if (deliveryDateStr != null) {
        final deliveryDate = DateTime.parse(deliveryDateStr);
        final now = DateTime.now();
        if (deliveryDate.year == now.year &&
            deliveryDate.month == now.month &&
            deliveryDate.day == now.day) {
          statusLabel = 'En camino';
          statusColor = Colors.purple;
        }
      }
    }

    return GestureDetector(
      onTap: () =>
          Navigator.pushNamed(context, '/order_detail', arguments: order['id']).then((_) => _loadOrders()),
      child: Container(
        margin: const EdgeInsets.only(bottom: 15),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey[100]!),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Pedido #${order['id']}',
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 16)),
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
                color: statusColor.withOpacity(0.1),
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
      ),
    );
  }
}
