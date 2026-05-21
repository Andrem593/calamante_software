import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../models/models.dart';
import 'package:geolocator/geolocator.dart';
import 'package:signature/signature.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'dart:typed_data';

class ReviewOrderScreen extends StatefulWidget {
  final Client client;
  final Branch? branch;
  final List<Map<String, dynamic>> items;
  final List<Product> products;
  final Position? position;
  final String notes;
  final String? address;
  final String? branchEmail;
  final String? paymentMethod;
  final int? creditDays;
  final DateTime? deliveryDate;

  const ReviewOrderScreen({
    super.key,
    required this.client,
    this.branch,
    required this.items,
    required this.products,
    this.position,
    required this.notes,
    this.address,
    this.branchEmail,
    this.paymentMethod,
    this.creditDays,
    this.deliveryDate,
  });

  @override
  State<ReviewOrderScreen> createState() => _ReviewOrderScreenState();
}

class _ReviewOrderScreenState extends State<ReviewOrderScreen> {
  bool _submitting = false;

  double get subtotal => widget.items
      .fold(0, (sum, item) => sum + (item['price'] * item['quantity']));

  double get taxes {
    double totalTax = 0;
    for (var item in widget.items) {
      final product = widget.products.firstWhere(
        (p) => p.id == item['product_id'],
        orElse: () => Product(
          id: 0,
          name: '',
          price: 0,
          stock: 0,
          taxPercentage: 0,
        ),
      );
      totalTax +=
          (item['price'] * item['quantity']) * (product.taxPercentage / 100);
    }
    return totalTax;
  }

  double get total => subtotal + taxes;

  final TextEditingController _requestedByNameCtrl = TextEditingController();
  final TextEditingController _requestedByIdCtrl = TextEditingController();
  late SignatureController _signatureController;

  @override
  void initState() {
    super.initState();
    _signatureController = SignatureController(
      penStrokeWidth: 3,
      penColor: Colors.black,
      exportBackgroundColor: Colors.white,
    );
  }

  @override
  void dispose() {
    _requestedByNameCtrl.dispose();
    _requestedByIdCtrl.dispose();
    _signatureController.dispose();
    super.dispose();
  }

  Future<void> _confirmOrder() async {
    if (_requestedByNameCtrl.text.isEmpty || _requestedByIdCtrl.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Por favor completa los datos del solicitante'),
            backgroundColor: Colors.orange),
      );
      return;
    }

    if (_signatureController.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Por favor firma para aceptar el pedido'),
            backgroundColor: Colors.orange),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final Uint8List? signatureBytes = await _signatureController.toPngBytes();
      String? signatureBase64;
      if (signatureBytes != null) {
        signatureBase64 = base64Encode(signatureBytes);
      }

      await context.read<ApiService>().post('/orders', {
        'client_id': widget.client.id,
        'branch_id': widget.branch?.id,
        'branch_email': widget.branchEmail,
        'items': widget.items,
        'latitude': widget.position?.latitude,
        'longitude': widget.position?.longitude,
        'notes': widget.notes,
        'address': widget.address,
        'payment_method': widget.paymentMethod,
        'credit_days': widget.creditDays,
        'requested_by_name': _requestedByNameCtrl.text,
        'requested_by_id': _requestedByIdCtrl.text,
        'signature': signatureBase64,
        'delivery_date': widget.deliveryDate?.toIso8601String().split('T')[0],
      });

      // Limpiar borradores al confirmar pedido con éxito
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('draft_client_id');
      await prefs.remove('draft_cart');
      await prefs.remove('draft_notes');
      await prefs.remove('draft_address');
      await prefs.remove('draft_payment_method');

      Navigator.of(context)
          .pushNamedAndRemoveUntil('/dashboard', (route) => false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('¡Pedido confirmado con éxito!'),
            backgroundColor: Colors.green),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text('Error al confirmar: $e'),
            backgroundColor: Colors.red),
      );
    } finally {
      setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Revisar Pedido',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _submitting
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Productos',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 15),
                  ...widget.items.map((item) {
                    final product = widget.products.firstWhere(
                        (p) => p.id == item['product_id'],
                        orElse: () => Product(
                            id: 0, name: 'Desconocido', price: 0, stock: 0));
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(product.name,
                                    style: const TextStyle(
                                        fontSize: 16,
                                        fontWeight: FontWeight.w500),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis),
                                Text('${item['quantity']} unidades',
                                    style: const TextStyle(color: Colors.grey)),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                              '\$${(item['price'] * item['quantity']).toStringAsFixed(2)}',
                              style: const TextStyle(
                                  fontSize: 16, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    );
                  }).toList(),
                  const Divider(height: 40),
                  const Text('Detalles del Pedido',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 15),
                  _buildSummaryRow(
                      'Subtotal', '\$${subtotal.toStringAsFixed(2)}'),
                  _buildSummaryRow('IVA', '\$${taxes.toStringAsFixed(2)}'),
                  _buildSummaryRow('Total', '\$${total.toStringAsFixed(2)}',
                      isTotal: true),
                  const Divider(height: 40),
                  const Text('Ubicación de Entrega',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 15),
                  Container(
                    height: 150,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.grey[200]!),
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: GoogleMap(
                        initialCameraPosition: CameraPosition(
                          target: LatLng(widget.position?.latitude ?? 0,
                              widget.position?.longitude ?? 0),
                          zoom: 15,
                        ),
                        markers: {
                          Marker(
                            markerId: const MarkerId('delivery'),
                            position: LatLng(widget.position?.latitude ?? 0,
                                widget.position?.longitude ?? 0),
                          ),
                        },
                        liteModeEnabled: true,
                        scrollGesturesEnabled: false,
                        zoomGesturesEnabled: false,
                      ),
                    ),
                  ),
                  const SizedBox(height: 25),
                  const Text('Información del Cliente',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 10),
                  Text(widget.client.name,
                      style: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.w500)),
                  Text(widget.client.email ?? '',
                      style: const TextStyle(color: Colors.grey)),
                  if (widget.branch != null) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Icon(Icons.store,
                            size: 16, color: Colors.lightBlue),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Sucursal: ${widget.branch!.name}',
                            style: const TextStyle(
                                fontSize: 14,
                                color: Color(0xFF0277BD),
                                fontWeight: FontWeight.w500),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                  if (widget.notes.isNotEmpty) ...[
                    const SizedBox(height: 15),
                    const Text('Observaciones:',
                        style: TextStyle(fontWeight: FontWeight.bold)),
                    Text(widget.notes,
                        style: const TextStyle(fontStyle: FontStyle.italic)),
                  ],
                  const Divider(height: 40),
                  const Text('Datos del Solicitante',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 15),
                  TextField(
                    controller: _requestedByNameCtrl,
                    decoration: InputDecoration(
                      labelText: 'Nombre de quien solicita',
                      fillColor: const Color(0xFFF1FBFE),
                      filled: true,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide.none,
                      ),
                    ),
                  ),
                  const SizedBox(height: 15),
                  TextField(
                    controller: _requestedByIdCtrl,
                    decoration: InputDecoration(
                      labelText: 'Número de Identificación',
                      fillColor: const Color(0xFFF1FBFE),
                      filled: true,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide.none,
                      ),
                    ),
                  ),
                  const SizedBox(height: 25),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Firma de Aceptación',
                          style: TextStyle(
                              fontSize: 18, fontWeight: FontWeight.bold)),
                      TextButton.icon(
                        onPressed: () => _signatureController.clear(),
                        icon: const Icon(Icons.clear, color: Colors.red),
                        label: const Text('Limpiar',
                            style: TextStyle(color: Colors.red)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Container(
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey[300]!),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Signature(
                        controller: _signatureController,
                        height: 150,
                        backgroundColor: const Color(0xFFF1FBFE),
                      ),
                    ),
                  ),
                  const SizedBox(height: 40),
                  SizedBox(
                    width: double.infinity,
                    height: 55,
                    child: ElevatedButton(
                      onPressed: _confirmOrder,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF03A9F4),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Confirmar Pedido',
                          style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.bold)),
                    ),
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            ),
    );
  }

  Widget _buildSummaryRow(String label, String value, {bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: TextStyle(
                  fontSize: isTotal ? 18 : 16,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
                  color: isTotal ? Colors.black : Colors.grey[700])),
          Text(value,
              style: TextStyle(
                  fontSize: isTotal ? 18 : 16,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
                  color: isTotal ? const Color(0xFF03A9F4) : Colors.black)),
        ],
      ),
    );
  }
}
