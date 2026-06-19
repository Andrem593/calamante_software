import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../services/api_service.dart';
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
                                        _order['latitude']?.toString() ?? '0') ??
                                    0.0,
                                double.tryParse(
                                        _order['longitude']?.toString() ?? '0') ??
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
                          ...((_order['items'] as List?) ?? [])
                              .map((item) => ListTile(
                                    contentPadding: EdgeInsets.zero,
                                    title: Text(
                                        item['product']?['name'] ?? 'Producto',
                                        style: const TextStyle(
                                            fontWeight: FontWeight.bold)),
                                    subtitle: Text('Pedido #${_order['id']}'),
                                    trailing: Text('${item['quantity']} unidades',
                                        style:
                                            const TextStyle(color: Colors.grey)),
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
                                        style:
                                            const TextStyle(color: Colors.grey)),
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
                                      fontSize: 14, fontWeight: FontWeight.bold)),
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
                                  base64Decode(_order['signature'].toString().contains(',')
                                      ? _order['signature'].toString().split(',').last
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
}
