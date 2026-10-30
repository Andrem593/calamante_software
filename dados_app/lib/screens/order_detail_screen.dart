import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:geolocator/geolocator.dart';
import '../services/api_service.dart';
import '../services/offline_database_service.dart';
import '../services/sync_service.dart';
import 'dart:convert';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  dynamic _order;
  bool _loading = true;
  bool _loadingRide = false;
  bool _isQuickCensusing = false;
  bool _hasQuickCensused = false;

  @override
  void initState() {
    super.initState();
    _loadOrderDetail();
  }

  Future<void> _loadOrderDetail() async {
    setState(() => _loading = true);
    try {
      final data =
          await context.read<ApiService>().get('/orders/${widget.orderId}');
      setState(() => _order = data);
    } catch (e) {
      print(e);
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _showPhoneDialog(String initialPhone, Function(String) onConfirm) async {
    final controller = TextEditingController(text: initialPhone);
    return showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Enviar por WhatsApp'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ingresa el número de teléfono del cliente (con código de país, ej: 593...):',
              style: TextStyle(fontSize: 13, color: Colors.grey),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                hintText: 'Número de WhatsApp',
                prefixIcon: const Icon(Icons.phone, color: Colors.green),
                fillColor: const Color(0xFFF1FBFE),
                filled: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancelar', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              onConfirm(controller.text.trim());
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: const Text('Compartir'),
          ),
        ],
      ),
    );
  }

  Future<void> _handleRideAction({required bool shareOnWhatsapp}) async {
    setState(() => _loadingRide = true);
    try {
      final api = context.read<ApiService>();
      final token = api.token;
      final baseUrl = ApiService.baseUrl;
      final orderId = _order['id'];
      final contificoId = _order['contifico_id'];

      String documentUrl = '';
      bool isOfficialRide = false;

      if (contificoId != null) {
        try {
          final doc = await api.get('/contifico/documento/$contificoId');
          final urlRide = doc['url_ride'];
          if (urlRide != null && urlRide.toString().isNotEmpty) {
            documentUrl = urlRide.toString();
            isOfficialRide = true;
          }
        } catch (_) {}
      }

      // Si no hay RIDE oficial de Contifico (no autorizado aún, prefactura o no sincronizado), usar Vista Previa PDF del backend
      if (documentUrl.isEmpty) {
        documentUrl = '$baseUrl/orders/$orderId/pdf?token=$token';
      }

      final uri = Uri.parse(documentUrl);

      if (shareOnWhatsapp) {
        String initialPhone = '';
        if (_order['client'] != null && _order['client']['phone'] != null) {
          initialPhone = _order['client']['phone'].toString().replaceAll(RegExp(r'\D'), '');
          if (initialPhone.isNotEmpty && !initialPhone.startsWith('593')) {
            if (initialPhone.startsWith('0')) {
              initialPhone = '593${initialPhone.substring(1)}';
            } else {
              initialPhone = '593$initialPhone';
            }
          }
        }

        await _showPhoneDialog(initialPhone, (phone) async {
          String finalPhone = phone.replaceAll(RegExp(r'\D'), '');
          final docTypeLabel = isOfficialRide ? 'RIDE de la factura' : 'Vista Previa del Pedido';
          final text = Uri.encodeComponent('Hola, te comparto el $docTypeLabel #$orderId: $documentUrl');
          final whatsappUrl = Uri.parse(
            finalPhone.isNotEmpty
                ? 'https://wa.me/$finalPhone?text=$text'
                : 'https://wa.me/?text=$text'
          );

          if (!await launchUrl(whatsappUrl, mode: LaunchMode.externalApplication)) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('No se pudo abrir WhatsApp'), backgroundColor: Colors.red),
            );
          }
        });
      } else {
        if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
          throw Exception('No se pudo abrir el documento PDF');
        }
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: ${e.toString().replaceAll('Exception: ', '')}'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      setState(() => _loadingRide = false);
    }
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Seguimiento del pedido',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SafeArea(
              top: false,
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Mapa superior
                    SizedBox(
                      height: 200,
                      child: GoogleMap(
                        initialCameraPosition: CameraPosition(
                          target: LatLng(
                              double.tryParse(
                                      _order['latitude']?.toString() ?? '0') ??
                                  0.0,
                              double.tryParse(
                                      _order['longitude']?.toString() ?? '0') ??
                                  0.0),
                          zoom: 15,
                        ),
                        markers: {
                          Marker(
                            markerId: const MarkerId('order_loc'),
                            position: LatLng(
                                double.tryParse(
                                        _order['latitude']?.toString() ??
                                            '0') ??
                                    0.0,
                                double.tryParse(
                                        _order['longitude']?.toString() ??
                                            '0') ??
                                    0.0),
                          ),
                        },
                        liteModeEnabled: true,
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildQuickCensusBanner(),
                          const SizedBox(height: 16),
                          const Text('Estado del pedido',
                              style: TextStyle(
                                  fontSize: 20, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 20),
                          if (_order['status'] == 'cancelled') ...[
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(12),
                              margin: const EdgeInsets.only(bottom: 20),
                              decoration: BoxDecoration(
                                color: Colors.red[50],
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: Colors.red[200]!),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.cancel, color: Colors.red),
                                  const SizedBox(width: 10),
                                  const Text('Este pedido ha sido anulado.',
                                      style: TextStyle(
                                          color: Colors.red,
                                          fontWeight: FontWeight.bold)),
                                ],
                              ),
                            ),
                          ],
                          _buildTimeline(),
                          const SizedBox(height: 30),
                          const Text('Detalles del pedido',
                              style: TextStyle(
                                  fontSize: 20, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 10),
                          ...((_order['items'] as List?) ?? []).map((item) =>
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(
                                    item['product']?['name'] ?? 'Producto',
                                    style: const TextStyle(
                                        fontWeight: FontWeight.bold)),
                                subtitle: Text('Pedido #${_order['id']}'),
                                trailing: Text('${item['quantity']} unidades',
                                    style: const TextStyle(color: Colors.grey)),
                              )),
                          const Divider(height: 40),
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF1FBFE),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.location_on,
                                    color: Color(0xFF03A9F4)),
                              ),
                              const SizedBox(width: 15),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Datos de Entrega',
                                        style: TextStyle(
                                            fontWeight: FontWeight.bold)),
                                    Text(
                                        'Dirección: ${_order['address'] ?? 'Calle Principal 123'}',
                                        style: const TextStyle(
                                            color: Colors.grey)),
                                    Text(
                                        'Pago: ${(_order['payment_method'] ?? 'Efectivo').toUpperCase()}',
                                        style: const TextStyle(
                                            color: Color(0xFF03A9F4),
                                            fontWeight: FontWeight.bold,
                                            fontSize: 12)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const Divider(height: 40),
                           SwitchListTile(
                            title: const Text('Pedido Facturado',
                                style: TextStyle(fontWeight: FontWeight.bold)),
                            subtitle: const Text(
                                'Marcar si el sistema ya procesó la factura'),
                            value: _order['is_invoiced'] == true ||
                                _order['is_invoiced'] == 1,
                            activeColor: const Color(0xFF03A9F4),
                            onChanged: (val) => _toggleInvoiced(val),
                          ),
                          const SizedBox(height: 15),
                          Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: const Color(0xFFE3F2FD),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFF90CAF9)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.description, color: Color(0xFF1976D2)),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        _order['contifico_id'] != null
                                            ? 'Factura Contifico: ${_order['is_invoiced'] == true || _order['is_invoiced'] == 1 ? "Autorizada/Generada" : "Prefactura"}'
                                            : 'Comprobante de Pedido #${_order['id']}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: Color(0xFF1565C0),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: ElevatedButton.icon(
                                        onPressed: _loadingRide 
                                            ? null 
                                            : () => _handleRideAction(shareOnWhatsapp: false),
                                        icon: const Icon(Icons.picture_as_pdf, size: 18),
                                        label: Text(_order['contifico_id'] != null ? 'Ver RIDE / PDF' : 'Ver PDF'),
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: const Color(0xFF1976D2),
                                          foregroundColor: Colors.white,
                                          elevation: 0,
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: ElevatedButton.icon(
                                        onPressed: _loadingRide 
                                            ? null 
                                            : () => _handleRideAction(shareOnWhatsapp: true),
                                        icon: const Icon(Icons.share, size: 18),
                                        label: const Text('WhatsApp'),
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: const Color(0xFF2E7D32),
                                          foregroundColor: Colors.white,
                                          elevation: 0,
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                if (_loadingRide) ...[
                                  const SizedBox(height: 10),
                                  const LinearProgressIndicator(),
                                ]
                              ],
                            ),
                          ),

                          if (_order['requested_by_name'] != null) ...[
                            const Divider(height: 40),
                            const Text('Solicitado por',
                                style: TextStyle(
                                    fontSize: 20, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 10),
                            Row(
                              children: [
                                const Icon(Icons.person_outline,
                                    color: Colors.grey, size: 20),
                                const SizedBox(width: 10),
                                Text('${_order['requested_by_name']}',
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w500)),
                              ],
                            ),
                            Row(
                              children: [
                                const Icon(Icons.badge_outlined,
                                    color: Colors.grey, size: 20),
                                const SizedBox(width: 10),
                                Text('ID: ${_order['requested_by_id']}',
                                    style: const TextStyle(color: Colors.grey)),
                              ],
                            ),
                            if (_order['signature'] != null) ...[
                              const SizedBox(height: 15),
                              const Text('Firma de Aceptación:',
                                  style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.bold)),
                              const SizedBox(height: 10),
                              Container(
                                height: 100,
                                width: double.infinity,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF1FBFE),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: Colors.grey[200]!),
                                ),
                                child: Image.memory(
                                  base64Decode(_order['signature']
                                          .toString()
                                          .contains(',')
                                      ? _order['signature']
                                          .toString()
                                          .split(',')
                                          .last
                                      : _order['signature']),
                                  fit: BoxFit.contain,
                                ),
                              ),
                            ],
                          ],
                          if (_order['notes'] != null &&
                              _order['notes'].toString().isNotEmpty) ...[
                            const Divider(height: 40),
                            const Text('Observaciones',
                                style: TextStyle(
                                    fontSize: 20, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 10),
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1FBFE),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                _order['notes'],
                                style: const TextStyle(
                                    fontStyle: FontStyle.italic,
                                    color: Colors.black87),
                              ),
                            ),
                          ],
                          const SizedBox(height: 30),
                          if (_order['status'] != 'delivered' &&
                              _order['status'] != 'cancelled')
                            SizedBox(
                              width: double.infinity,
                              height: 50,
                              child: ElevatedButton.icon(
                                onPressed: _markOrderAsDelivered,
                                icon: const Icon(Icons.check_circle_outline,
                                    color: Colors.white),
                                label: const Text('Entregar Pedido',
                                    style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.bold)),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.green,
                                  shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12)),
                                ),
                              ),
                            ),
                          const SizedBox(height: 20),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Future<void> _markOrderAsDelivered() async {
    bool? confirm = true;
    if (!(_order['is_invoiced'] == true || _order['is_invoiced'] == 1)) {
      confirm = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Atención'),
          content: const Text(
              'Este pedido aún no aparece como facturado. ¿Estás seguro de que deseas entregarlo?'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancelar')),
            TextButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Entregar de todas formas')),
          ],
        ),
      );
    }

    if (confirm != true) return;

    setState(() => _loading = true);
    try {
      await context
          .read<ApiService>()
          .post('/orders/${widget.orderId}/deliver', {});
      await _loadOrderDetail();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Pedido entregado con éxito'),
            backgroundColor: Colors.green),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text('Error al entregar: $e'),
            backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _toggleInvoiced(bool value) async {
    setState(() => _loading = true);
    try {
      await context
          .read<ApiService>()
          .post('/orders/${widget.orderId}/invoiced', {
        'is_invoiced': value,
      });
      await _loadOrderDetail();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text(value
                ? 'Pedido marcado como facturado'
                : 'Pedido marcado como pendiente de factura'),
            backgroundColor: Colors.blue),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text('Error al actualizar: $e'),
            backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _loading = false);
    }
  }

  Widget _buildTimeline() {
    final status = _order['status'];
    final deliveryDateStr = _order['delivery_date'];
    DateTime? deliveryDate =
        deliveryDateStr != null ? DateTime.parse(deliveryDateStr) : null;
    final now = DateTime.now();
    bool isToday = deliveryDate != null &&
        deliveryDate.year == now.year &&
        deliveryDate.month == now.month &&
        deliveryDate.day == now.day;

    final statuses = [
      {'label': 'En proceso (Recibido)', 'completed': true},
      {
        'label': 'Pedido Facturado',
        'completed': status == 'invoiced' ||
            status == 'on_the_way' ||
            status == 'delivered',
        'isCurrent': status == 'invoiced'
      },
      {
        'label': 'En camino',
        'completed': status == 'delivered',
        'isCurrent': (status == 'pending' || status == 'invoiced') && isToday
      },
      {
        'label': 'Entregado',
        'completed': status == 'delivered',
        'isCurrent': status == 'delivered'
      },
    ];

    return Column(
      children: statuses.asMap().entries.map((entry) {
        int idx = entry.key;
        var s = entry.value;
        bool isLast = idx == statuses.length - 1;

        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Column(
              children: [
                Icon(
                  s['completed'] == true
                      ? Icons.check_circle
                      : (s['isCurrent'] == true
                          ? Icons.local_shipping
                          : Icons.circle_outlined),
                  color: (s['completed'] == true || s['isCurrent'] == true)
                      ? const Color(0xFF03A9F4)
                      : Colors.grey[300],
                  size: 24,
                ),
                if (!isLast)
                  Container(
                    width: 2,
                    height: 40,
                    color: s['completed'] == true
                        ? const Color(0xFF03A9F4)
                        : Colors.grey[200],
                  ),
              ],
            ),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(s['label'] as String,
                      style: TextStyle(
                        fontWeight:
                            (s['completed'] == true || s['isCurrent'] == true)
                                ? FontWeight.bold
                                : FontWeight.normal,
                        color:
                            (s['completed'] == true || s['isCurrent'] == true)
                                ? Colors.black
                                : Colors.grey,
                        fontSize: 16,
                      )),
                  if (!isLast) const SizedBox(height: 35),
                ],
              ),
            ),
          ],
        );
      }).toList(),
    );
  }

  Future<void> _quickCensusCurrentLocation() async {
    final clientId = _order['client_id'] ?? _order['client']?['id'];
    if (clientId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo identificar el cliente del pedido.'), backgroundColor: Colors.red),
      );
      return;
    }

    setState(() => _isQuickCensusing = true);

    try {
      Position? currentPos;
      try {
        currentPos = await Geolocator.getCurrentPosition(
          timeLimit: const Duration(seconds: 10),
          desiredAccuracy: LocationAccuracy.high,
        );
      } catch (e) {
        debugPrint('Error obteniendo GPS actual: $e');
      }

      final double? lat = currentPos?.latitude ?? double.tryParse(_order['latitude']?.toString() ?? '');
      final double? lng = currentPos?.longitude ?? double.tryParse(_order['longitude']?.toString() ?? '');

      if (lat == null || lng == null || (lat == 0 && lng == 0)) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('No se pudo obtener una ubicación GPS válida. Activa el GPS del teléfono.'),
              backgroundColor: Colors.red,
            ),
          );
        }
        return;
      }

      final branchId = _order['branch_id'] ?? _order['branch']?['id'];
      final clientName = _order['client']?['name'] ?? 'Cliente';
      final branchName = _order['branch']?['name'];

      final syncService = context.read<SyncService>();
      final apiService = context.read<ApiService>();

      final payload = {
        'client_id': clientId,
        if (branchId != null) 'branch_id': branchId,
        'latitude': lat,
        'longitude': lng,
      };

      if (syncService.isOnline) {
        await apiService.saveBranchCensus(payload, clientId: clientId);
      } else {
        await OfflineDatabaseService.instance.insertOfflineCensus(
          payload,
          clientId: clientId,
          clientName: clientName,
          branchId: branchId,
        );
        syncService.checkPendingCounts();
      }

      setState(() {
        _hasQuickCensused = true;
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.white),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    branchName != null
                        ? '¡Ubicación de "$branchName" actualizada con tu GPS!'
                        : '¡Ubicación de "$clientName" actualizada con tu GPS!',
                  ),
                ),
              ],
            ),
            backgroundColor: Colors.green[700],
            duration: const Duration(seconds: 3),
          ),
        );
      }
    } catch (e) {
      debugPrint('Error en Quick Census: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al actualizar ubicación: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isQuickCensusing = false);
    }
  }

  Widget _buildQuickCensusBanner() {
    final bool isGeocoded = _hasQuickCensused ||
        (_order['branch']?['latitude'] != null) ||
        (_order['latitude'] != null && _order['latitude'] != 0);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isGeocoded ? const Color(0xFFE8F5E9) : const Color(0xFFFFF8E1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isGeocoded ? const Color(0xFFA5D6A7) : const Color(0xFFFFE082),
        ),
      ),
      child: Row(
        children: [
          Icon(
            isGeocoded ? Icons.check_circle_rounded : Icons.add_location_alt_rounded,
            color: isGeocoded ? Colors.green[700] : Colors.orange[800],
            size: 24,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isGeocoded ? 'Ubicación Registrada' : '¿Entregando en el local?',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: isGeocoded ? Colors.green[900] : Colors.orange[900],
                  ),
                ),
                Text(
                  isGeocoded
                      ? 'Puedes re-fijar las coordenadas oficiales con 1 clic si estás en la puerta.'
                      : 'Fija esta ubicación GPS actual como la oficial del cliente con 1 clic.',
                  style: TextStyle(fontSize: 11, color: Colors.grey[700]),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          ElevatedButton(
            onPressed: _isQuickCensusing ? null : _quickCensusCurrentLocation,
            style: ElevatedButton.styleFrom(
              backgroundColor: isGeocoded ? Colors.green[600] : const Color(0xFF03A9F4),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: _isQuickCensusing
                ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Text(
                    isGeocoded ? 'Actualizar GPS' : 'Fijar GPS',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                  ),
          ),
        ],
      ),
    );
  }
}
