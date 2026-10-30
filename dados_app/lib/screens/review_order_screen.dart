import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/offline_database_service.dart';
import '../services/sync_service.dart';
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
  final bool isDirectInvoice;
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
    this.isDirectInvoice = false,
    this.deliveryDate,
  });

  @override
  State<ReviewOrderScreen> createState() => _ReviewOrderScreenState();
}

class _ReviewOrderScreenState extends State<ReviewOrderScreen> {
  bool _submitting = false;
  bool _isQuickCensusing = false;
  bool _hasQuickCensused = false;

  Future<void> _quickCensusCurrentLocation() async {
    if (widget.position == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se dispone de coordenadas GPS del pedido.'), backgroundColor: Colors.red),
      );
      return;
    }

    setState(() => _isQuickCensusing = true);

    try {
      final syncService = context.read<SyncService>();
      final apiService = context.read<ApiService>();

      final payload = {
        'client_id': widget.client.id,
        if (widget.branch != null) 'branch_id': widget.branch!.id,
        'latitude': widget.position!.latitude,
        'longitude': widget.position!.longitude,
      };

      if (syncService.isOnline) {
        await apiService.saveBranchCensus(payload, clientId: widget.client.id);
      } else {
        await OfflineDatabaseService.instance.insertOfflineCensus(
          payload,
          clientId: widget.client.id,
          clientName: widget.client.name,
          branchId: widget.branch?.id,
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
                    widget.branch != null
                        ? '¡Ubicación de "${widget.branch!.name}" actualizada con tu GPS!'
                        : '¡Ubicación del cliente actualizada con tu GPS!',
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

  Widget _buildQuickCensusWidget() {
    final bool isGeocoded = (widget.branch?.isGeocoded ?? false) || _hasQuickCensused;

    return Container(
      margin: const EdgeInsets.only(top: 12),
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
                  isGeocoded ? 'Cliente / Sucursal Georreferenciada' : '¿Estás en el local del cliente?',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: isGeocoded ? Colors.green[900] : Colors.orange[900],
                  ),
                ),
                Text(
                  isGeocoded
                      ? 'Las coordenadas ya están fijadas. Puedes re-fijarlas si estás en su puerta.'
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

  double get subtotal => widget.items.fold(0, (sum, item) {
        final double price = double.parse(item['price'].toString());
        final int quantity = int.parse(item['quantity'].toString());
        final double discount =
            double.parse((item['discount_percentage'] ?? 0.0).toString());
        final double discountedPrice = price * (1 - discount / 100);
        return sum + (discountedPrice * quantity);
      });

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
      final double price = double.parse(item['price'].toString());
      final int quantity = int.parse(item['quantity'].toString());
      final double discount =
          double.parse((item['discount_percentage'] ?? 0.0).toString());
      final double discountedPrice = price * (1 - discount / 100);
      totalTax += (discountedPrice * quantity) * (product.taxPercentage / 100);
    }
    return totalTax;
  }

  double get total => subtotal + taxes;

  List<dynamic> _equipments = [];
  List<int> _selectedEquipmentIds = [];
  bool _loadingEquipments = false;

  Future<void> _loadEquipments() async {
    final api = context.read<ApiService>();
    try {
      final list = await api.getEquipments();
      if (!mounted) return;
      setState(() {
        _equipments = list;
      });
    } catch (e) {
      debugPrint('Error cargando catálogo de equipos: $e');
    }
  }

  Future<void> _loadClientEquipments() async {
    final api = context.read<ApiService>();
    setState(() {
      _loadingEquipments = true;
      _selectedEquipmentIds = [];
    });
    try {
      final branchId = widget.branch?.id;
      final data =
          await api.getClientEquipments(widget.client.id, branchId: branchId);
      if (!mounted) return;
      setState(() {
        _selectedEquipmentIds = data.map((j) => j['id'] as int).toList();
      });
    } catch (e) {
      debugPrint('Error cargando equipos del cliente: $e');
    } finally {
      if (mounted) setState(() => _loadingEquipments = false);
    }
  }

  Future<void> _openEquipmentModal() async {
    // Si todavía está cargando, mostrar diálogo de espera
    if (_loadingEquipments) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const AlertDialog(
          content: Row(
            children: [
              CircularProgressIndicator(),
              SizedBox(width: 16),
              Text('Cargando catálogo...'),
            ],
          ),
        ),
      );
      int attempts = 0;
      while (_loadingEquipments && attempts < 30) {
        await Future.delayed(const Duration(milliseconds: 100));
        attempts++;
      }
      if (!mounted) return;
      Navigator.of(context).pop();
    }

    // Si no cargaron (error o vacíos), intentar cargar de nuevo
    if (_equipments.isEmpty) {
      await _loadEquipments();
      if (!mounted) return;
    }

    final List<int>? result = await showModalBottomSheet<List<int>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EquipmentPickerModal(
        equipments: List<dynamic>.from(_equipments),
        initialSelected: List<int>.from(_selectedEquipmentIds),
      ),
    );

    if (result != null && mounted) {
      setState(() => _selectedEquipmentIds = result);
    }
  }

  Widget _buildClientEquipmentsSection() {
    if (_equipments.isEmpty && _loadingEquipments) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16.0),
          child: Center(
            child: SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
        ),
      );
    }

    return InkWell(
      onTap: _openEquipmentModal,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFFF0F8FF),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.lightBlue.shade200),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.blue[50],
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(Icons.kitchen_outlined,
                  color: Colors.blue[700], size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Equipos del Cliente',
                      style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: Colors.blue[900])),
                  const SizedBox(height: 3),
                  _loadingEquipments
                      ? const SizedBox(
                          height: 14,
                          width: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(
                          _selectedEquipmentIds.isEmpty
                              ? 'Toca para registrar equipos'
                              : '${_selectedEquipmentIds.length} equipo${_selectedEquipmentIds.length == 1 ? '' : 's'} registrado${_selectedEquipmentIds.length == 1 ? '' : 's'}',
                          style: TextStyle(
                            fontSize: 12,
                            color: _selectedEquipmentIds.isEmpty
                                ? Colors.grey[500]
                                : Colors.blue[700],
                          ),
                        ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: Colors.blue[400]),
          ],
        ),
      ),
    );
  }

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
    _loadEquipments();
    _loadClientEquipments();
  }

  @override
  void dispose() {
    _requestedByNameCtrl.dispose();
    _requestedByIdCtrl.dispose();
    _signatureController.dispose();
    super.dispose();
  }

  Future<void> _clearDrafts() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('draft_client_id');
    await prefs.remove('draft_cart');
    await prefs.remove('draft_notes');
    await prefs.remove('draft_address');
    await prefs.remove('draft_payment_method');
  }

  Future<void> _confirmOrder() async {
    if (_submitting) return;

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

      final payload = {
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
        'is_direct_invoice': widget.isDirectInvoice,
        'requested_by_name': _requestedByNameCtrl.text,
        'requested_by_id': _requestedByIdCtrl.text,
        'signature': signatureBase64,
        'delivery_date': widget.deliveryDate?.toIso8601String().split('T')[0],
        'created_at': DateTime.now().toIso8601String(),
      };

      final syncService = context.read<SyncService>();
      final apiService = context.read<ApiService>();
      final isOnline = syncService.isOnline;

      if (!isOnline) {
        await OfflineDatabaseService.instance.insertOfflineOrder(
          payload,
          clientId: widget.client.id,
          clientName: widget.client.name,
          total: total,
        );
        await syncService.checkPendingCounts();
        await _clearDrafts();

        if (!mounted) return;
        Navigator.of(context)
            .pushNamedAndRemoveUntil('/dashboard', (route) => false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('📱 Guardado localmente (Sin conexión). Puedes sincronizarlo individualmente desde el Historial.'),
            backgroundColor: Colors.orange,
            duration: Duration(seconds: 5),
          ),
        );
        return;
      }

      try {
        await apiService.post('/orders', payload);

        await apiService.syncClientEquipments(
            widget.client.id, _selectedEquipmentIds,
            branchId: widget.branch?.id);

        await _clearDrafts();

        if (!mounted) return;
        Navigator.of(context)
            .pushNamedAndRemoveUntil('/dashboard', (route) => false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('¡Pedido confirmado con éxito!'),
              backgroundColor: Colors.green),
        );
      } catch (e) {
        debugPrint('Error enviando pedido online. Guardando localmente: $e');
        await OfflineDatabaseService.instance.insertOfflineOrder(
          payload,
          clientId: widget.client.id,
          clientName: widget.client.name,
          total: total,
        );
        await syncService.checkPendingCounts();
        await _clearDrafts();

        if (!mounted) return;
        Navigator.of(context)
            .pushNamedAndRemoveUntil('/dashboard', (route) => false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('📱 Falló la conexión. El pedido fue guardado localmente; puedes sincronizarlo individualmente desde el Historial.'),
            backgroundColor: Colors.orange,
            duration: Duration(seconds: 5),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text('Error al procesar: $e'),
              backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
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
          : SafeArea(
              top: false,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Productos',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 15),
                    ...widget.items.map((item) {
                      final product = widget.products.firstWhere(
                          (p) => p.id == item['product_id'],
                          orElse: () => Product(
                              id: 0, name: 'Desconocido', price: 0, stock: 0));
                      final double price =
                          double.parse(item['price'].toString());
                      final int quantity =
                          int.parse(item['quantity'].toString());
                      final double discount = double.parse(
                          (item['discount_percentage'] ?? 0.0).toString());
                      final double discountedPrice =
                          price * (1 - discount / 100);
                      final double itemTotal = discountedPrice * quantity;

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
                                  Row(
                                    children: [
                                      Text('${item['quantity']} unidades',
                                          style: const TextStyle(
                                              color: Colors.grey)),
                                      if (discount > 0) ...[
                                        const SizedBox(width: 8),
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                              horizontal: 4, vertical: 1),
                                          decoration: BoxDecoration(
                                            color: Colors.amber[100],
                                            borderRadius:
                                                BorderRadius.circular(4),
                                          ),
                                          child: Text(
                                            '-${discount.toStringAsFixed(1)}%',
                                            style: TextStyle(
                                              color: Colors.amber[800],
                                              fontWeight: FontWeight.bold,
                                              fontSize: 9,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text('\$${itemTotal.toStringAsFixed(2)}',
                                    style: const TextStyle(
                                        fontSize: 16,
                                        fontWeight: FontWeight.bold)),
                                if (discount > 0)
                                  Text(
                                    '(\$${discountedPrice.toStringAsFixed(2)} c/u)',
                                    style: const TextStyle(
                                        color: Colors.grey, fontSize: 11),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                    const Divider(height: 40),
                    const Text('Detalles del Pedido',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold)),
                    _buildSummaryRow(
                        'Subtotal', '\$${subtotal.toStringAsFixed(2)}'),
                    _buildSummaryRow('IVA', '\$${taxes.toStringAsFixed(2)}'),
                    _buildSummaryRow('Total', '\$${total.toStringAsFixed(2)}',
                        isTotal: true),
                    const SizedBox(height: 6),
                    _buildSummaryRow(
                        'Condición de Pago',
                        widget.paymentMethod == 'Crédito'
                            ? 'Crédito (${widget.creditDays ?? 30} días)'
                            : (widget.paymentMethod ?? 'Efectivo (Contado)')),
                    const Divider(height: 40),
                    const Text('Ubicación de Entrega',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold)),
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
                    _buildQuickCensusWidget(),
                    const SizedBox(height: 25),
                    const Text('Información del Cliente',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold)),
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
                    const SizedBox(height: 20),
                    _buildClientEquipmentsSection(),
                    if (widget.notes.isNotEmpty) ...[
                      const SizedBox(height: 15),
                      const Text('Observaciones:',
                          style: TextStyle(fontWeight: FontWeight.bold)),
                      Text(widget.notes,
                          style: const TextStyle(fontStyle: FontStyle.italic)),
                    ],
                    const Divider(height: 40),
                    const Text('Datos del Solicitante',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.bold)),
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
                        onPressed: _submitting ? null : _confirmOrder,
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

// ─── Modal reutilizable para seleccionar equipos ───────────────────────────────
class _EquipmentPickerModal extends StatefulWidget {
  final List<dynamic> equipments;
  final List<int> initialSelected;

  const _EquipmentPickerModal({
    required this.equipments,
    required this.initialSelected,
  });

  @override
  State<_EquipmentPickerModal> createState() => _EquipmentPickerModalState();
}

class _EquipmentPickerModalState extends State<_EquipmentPickerModal> {
  late List<int> _selected;
  final TextEditingController _searchCtrl = TextEditingController();
  String _query = '';

  @override
  void initState() {
    super.initState();
    _selected = List<int>.from(widget.initialSelected);
    _searchCtrl.addListener(
        () => setState(() => _query = _searchCtrl.text.toLowerCase()));
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  List<dynamic> get _filtered => widget.equipments.where((eq) {
        final model = (eq['model'] as String).toLowerCase();
        final cap = (eq['capacity'] as String).toLowerCase();
        return model.contains(_query) || cap.contains(_query);
      }).toList();

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      minChildSize: 0.5,
      expand: false,
      builder: (_, scrollCtrl) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            // Handle
            const SizedBox(height: 12),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 16),
            // Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.blue[50],
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(Icons.kitchen_outlined,
                        color: Colors.blue[700], size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Equipos del Cliente',
                            style: TextStyle(
                                fontWeight: FontWeight.bold, fontSize: 17)),
                        Text(
                            '${_selected.length} seleccionado${_selected.length == 1 ? '' : 's'}',
                            style: TextStyle(
                                fontSize: 12, color: Colors.blue[600])),
                      ],
                    ),
                  ),
                  TextButton(
                    onPressed: () => setState(() => _selected.clear()),
                    child: const Text('Limpiar',
                        style: TextStyle(color: Colors.red)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            // Search bar
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: TextField(
                controller: _searchCtrl,
                decoration: InputDecoration(
                  hintText: 'Buscar equipo...',
                  prefixIcon: const Icon(Icons.search, color: Colors.grey),
                  filled: true,
                  fillColor: Colors.grey[100],
                  contentPadding:
                      const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            const Divider(height: 1),
            // Equipment list
            Expanded(
              child: filtered.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.search_off_rounded,
                              size: 48, color: Colors.grey[300]),
                          const SizedBox(height: 8),
                          Text('No se encontraron equipos',
                              style: TextStyle(
                                  color: Colors.grey[500], fontSize: 14)),
                        ],
                      ),
                    )
                  : ListView.separated(
                      controller: scrollCtrl,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 8),
                      itemCount: filtered.length,
                      separatorBuilder: (_, __) =>
                          const Divider(height: 1, indent: 60),
                      itemBuilder: (_, i) {
                        final eq = filtered[i];
                        final id = eq['id'] as int;
                        final isSelected = _selected.contains(id);
                        return Material(
                          color:
                              isSelected ? Colors.blue[50] : Colors.transparent,
                          borderRadius: BorderRadius.circular(12),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(12),
                            onTap: () => setState(() {
                              if (isSelected) {
                                _selected.remove(id);
                              } else {
                                _selected.add(id);
                              }
                            }),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 12, vertical: 12),
                              child: Row(
                                children: [
                                  AnimatedContainer(
                                    duration: const Duration(milliseconds: 200),
                                    width: 26,
                                    height: 26,
                                    decoration: BoxDecoration(
                                      color: isSelected
                                          ? const Color(0xFF03A9F4)
                                          : Colors.white,
                                      borderRadius: BorderRadius.circular(6),
                                      border: Border.all(
                                        color: isSelected
                                            ? const Color(0xFF03A9F4)
                                            : Colors.grey[300]!,
                                        width: 1.5,
                                      ),
                                    ),
                                    child: isSelected
                                        ? const Icon(Icons.check,
                                            color: Colors.white, size: 16)
                                        : null,
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(eq['model'] as String,
                                            style: const TextStyle(
                                                fontWeight: FontWeight.w600,
                                                fontSize: 14)),
                                        const SizedBox(height: 2),
                                        Text(eq['capacity'] as String,
                                            style: TextStyle(
                                                color: Colors.grey[600],
                                                fontSize: 12)),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
            ),
            // Confirm button
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(context, _selected),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF03A9F4),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14)),
                      elevation: 0,
                    ),
                    child: Text(
                      _selected.isEmpty
                          ? 'Confirmar sin equipos'
                          : 'Guardar ${_selected.length} equipo${_selected.length == 1 ? '' : 's'}',
                      style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 16),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
