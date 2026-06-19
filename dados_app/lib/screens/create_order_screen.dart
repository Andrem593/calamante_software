import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'dart:convert';
import '../services/api_service.dart';
import '../models/models.dart';

class CreateOrderScreen extends StatefulWidget {
  const CreateOrderScreen({super.key});

  @override
  State<CreateOrderScreen> createState() => _CreateOrderScreenState();
}

class _CreateOrderScreenState extends State<CreateOrderScreen> {
  // ─── Cliente ────────────────────────────────────────────────────────────────
  Client? _selectedClient;
  final TextEditingController _clientSearchCtrl = TextEditingController();
  List<Client> _searchResults = [];
  bool _isSearching = false;
  Timer? _debounce;

  // ─── Sucursal ────────────────────────────────────────────────────────────────
  List<Branch> _branches = [];
  Branch? _selectedBranch;
  bool _loadingBranches = false;
  String _branchSearchQuery = '';

  // ─── Productos ───────────────────────────────────────────────────────────────
  List<Product> _products = [];
  List<Category> _categories = [];
  int? _selectedCategoryId;
  final Map<int, int> _cart = {};

  // ─── Otros campos ────────────────────────────────────────────────────────────
  final TextEditingController _notesCtrl = TextEditingController();
  final TextEditingController _addressCtrl = TextEditingController();
  final TextEditingController _branchEmailCtrl = TextEditingController();
  final TextEditingController _creditDaysCtrl = TextEditingController(text: '30');
  String _paymentMethod = 'Efectivo';
  bool _isDirectInvoice = false;
  DateTime _deliveryDate = DateTime.now().add(const Duration(days: 1));
  Position? _currentPosition;
  bool _loading = false;
  GoogleMapController? _mapController;
  bool _initializing = true;

  @override
  void initState() {
    super.initState();
    debugPrint('CreateOrderScreen: initState');
    _loadInitialData();
  }

  @override
  void dispose() {
    _clientSearchCtrl.dispose();
    _notesCtrl.dispose();
    _addressCtrl.dispose();
    _branchEmailCtrl.dispose();
    _creditDaysCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _loadInitialData() async {
    debugPrint('CreateOrderScreen: _loadInitialData START');
    await _loadCategories();
    await _loadDraft();
    await _loadProducts(clientId: _selectedClient?.id);
    await _getCurrentLocation();
    debugPrint('CreateOrderScreen: _loadInitialData END');
    if (!mounted) return;
    setState(() {
      _initializing = false;
      debugPrint('CreateOrderScreen: _initializing = false');
    });
  }

  Future<void> _loadCategories() async {
    final api = context.read<ApiService>();
    try {
      final data = await api.get('/products/categories');
      if (!mounted) return;
      setState(() {
        _categories = (data as List).map((j) => Category.fromJson(j)).toList();
      });
    } catch (e) {
      debugPrint('Error cargando categorías: $e');
    }
  }

  // ── Carga únicamente productos (no clientes)
  Future<void> _loadProducts({int? clientId}) async {
    final api = context.read<ApiService>();
    try {
      final String endpoint = clientId != null ? '/products?client_id=$clientId' : '/products';
      final productsData = await api.get(endpoint);
      if (!mounted) return;
      setState(() {
        _products =
            (productsData as List).map((j) => Product.fromJson(j)).toList();
      });
    } catch (e) {
      debugPrint('Error cargando productos: $e');
    }
  }

  // ── Búsqueda de clientes (debounce 300 ms, mín. 2 caracteres)
  void _onSearchChanged(String value) {
    _debounce?.cancel();
    if (value.trim().length < 2) {
      setState(() => _searchResults = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      if (!mounted) return;
      setState(() => _isSearching = true);
      try {
        final api = context.read<ApiService>();
        final data = await api
            .get('/clients/search?q=${Uri.encodeComponent(value.trim())}');
        if (!mounted) return;
        setState(() {
          _searchResults =
              (data as List).map((j) => Client.fromJson(j)).toList();
        });
      } catch (e) {
        debugPrint('Error en búsqueda: $e');
      } finally {
        if (mounted) setState(() => _isSearching = false);
      }
    });
  }

  // ── Selección de cliente → carga sus sucursales
  Future<void> _selectClient(Client client) async {
    String displayName = client.nombreLocal != null && client.nombreLocal!.isNotEmpty
        ? client.nombreLocal!
        : (client.companyName != null && client.companyName!.isNotEmpty
            ? client.companyName!
            : client.name);

    setState(() {
      _selectedClient = client;
      _searchResults = [];
      _clientSearchCtrl.text = displayName;
      _branches = [];
      _selectedBranch = null;
      _loadingBranches = true;
    });
    _saveDraft();

    try {
      final api = context.read<ApiService>();
      final data = await api.get('/clients/${client.id}/branches');
      if (!mounted) return;
      setState(() {
        _branches = (data as List).map((j) => Branch.fromJson(j)).toList();
      });
      await _loadProducts(clientId: client.id);
    } catch (e) {
      debugPrint('Error cargando sucursales: $e');
    } finally {
      if (mounted) setState(() => _loadingBranches = false);
    }
  }

  // ── Deseleccionar cliente
  void _clearClient() {
    setState(() {
      _selectedClient = null;
      _clientSearchCtrl.clear();
      _searchResults = [];
      _branches = [];
      _selectedBranch = null;
    });
    _saveDraft();
    _loadProducts();
  }

  Future<void> _getCurrentLocation() async {
    debugPrint('CreateOrderScreen: Obteniendo ubicación...');
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        debugPrint('CreateOrderScreen: Localización deshabilitada');
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) return;
      }
      if (permission == LocationPermission.deniedForever) return;

      _currentPosition = await Geolocator.getCurrentPosition(
        timeLimit: const Duration(seconds: 10),
        desiredAccuracy: LocationAccuracy.high,
      );
      debugPrint('CreateOrderScreen: Ubicación obtenida: $_currentPosition');

      if (!mounted) return;
      if (_currentPosition != null && _mapController != null) {
        _mapController!.animateCamera(
          CameraUpdate.newLatLngZoom(
            LatLng(_currentPosition!.latitude, _currentPosition!.longitude),
            16,
          ),
        );
      }
      setState(() {});
      _saveDraft();
    } catch (e) {
      debugPrint('CreateOrderScreen: Error en Geolocator: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No se pudo obtener la ubicación precisa')),
        );
      }
    }
  }

  Future<void> _saveDraft() async {
    if (_initializing) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt('draft_client_id', _selectedClient?.id ?? -1);
    await prefs.setString('draft_client_name', _selectedClient?.name ?? '');
    await prefs.setInt('draft_branch_id', _selectedBranch?.id ?? -1);
    await prefs.setString('draft_cart',
        jsonEncode(_cart.map((k, v) => MapEntry(k.toString(), v))));
    await prefs.setString('draft_notes', _notesCtrl.text);
    await prefs.setString('draft_address', _addressCtrl.text);
    await prefs.setString('draft_payment_method', _paymentMethod);
    await prefs.setString('draft_branch_email', _branchEmailCtrl.text);
    await prefs.setString('draft_credit_days', _creditDaysCtrl.text);
    await prefs.setBool('draft_is_direct_invoice', _isDirectInvoice);
    if (_currentPosition != null) {
      await prefs.setDouble('draft_lat', _currentPosition!.latitude);
      await prefs.setDouble('draft_lng', _currentPosition!.longitude);
    }
    await prefs.setString(
        'draft_delivery_date', _deliveryDate.toIso8601String());
  }

  Future<void> _loadDraft() async {
    final prefs = await SharedPreferences.getInstance();
    final clientId = prefs.getInt('draft_client_id') ?? -1;
    final clientName = prefs.getString('draft_client_name') ?? '';
    final cartStr = prefs.getString('draft_cart');
    final deliveryDateStr = prefs.getString('draft_delivery_date');

    if (!mounted) return;
    setState(() {
      if (deliveryDateStr != null) {
        _deliveryDate = DateTime.parse(deliveryDateStr);
      }
      // Restaurar cliente desde draft (solo nombre e id — sin llamada al servidor aquí)
      if (clientId != -1 && clientName.isNotEmpty) {
        _selectedClient = Client(id: clientId, name: clientName);
        _clientSearchCtrl.text = clientName;
      }
      if (cartStr != null) {
        final Map<String, dynamic> decoded = jsonDecode(cartStr);
        _cart.clear();
        decoded.forEach((key, value) {
          _cart[int.parse(key)] = value as int;
        });
      }
      _notesCtrl.text = prefs.getString('draft_notes') ?? '';
      _addressCtrl.text = prefs.getString('draft_address') ?? '';
      _branchEmailCtrl.text = prefs.getString('draft_branch_email') ?? '';
      _paymentMethod = prefs.getString('draft_payment_method') ?? 'Efectivo';
      _creditDaysCtrl.text = prefs.getString('draft_credit_days') ?? '30';
      _isDirectInvoice = prefs.getBool('draft_is_direct_invoice') ?? false;
      
      final lat = prefs.getDouble('draft_lat');
      final lng = prefs.getDouble('draft_lng');
      if (lat != null && lng != null) {
        _currentPosition = Position(
          latitude: lat,
          longitude: lng,
          timestamp: DateTime.now(),
          accuracy: 0,
          altitude: 0,
          heading: 0,
          speed: 0,
          speedAccuracy: 0,
          altitudeAccuracy: 0,
          headingAccuracy: 0,
        );
      }
    });
  }

  void _goToReview() {
    if (_selectedClient == null || _cart.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Selecciona un cliente y productos')),
      );
      return;
    }

    final items = _cart.entries.where((e) => e.value > 0).map((e) {
      final product = _products.firstWhere((p) => p.id == e.key);
      return {
        'product_id': product.id,
        'quantity': e.value,
        'price': product.price,
        'discount_percentage': product.discountPercentage,
      };
    }).toList();

    if (items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Debes seleccionar al menos un producto')),
      );
      return;
    }

    Navigator.pushNamed(context, '/review_order', arguments: {
      'client': _selectedClient,
      'branch': _selectedBranch,
      'items': items,
      'products': _products,
      'position': _currentPosition,
      'notes': _notesCtrl.text,
      'address': _addressCtrl.text,
      'branch_email': _branchEmailCtrl.text,
      'payment_method': _paymentMethod,
      'credit_days': int.tryParse(_creditDaysCtrl.text) ?? 30,
      'is_direct_invoice': _isDirectInvoice,
      'delivery_date': _deliveryDate,
    });
  }

  // ─────────────────────────────────────────────────────────────────────────────
  //  BUILD
  // ─────────────────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Tomar Pedido',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: _loading || _initializing
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.all(20.0),
                    children: [
                      // ── Búsqueda de cliente ─────────────────────────────────
                      const Text('Detalles del Cliente',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 10),
                      _buildClientSearch(),
                      const SizedBox(height: 12),

                      // ── Selector de sucursal ────────────────────────────────
                      if (_selectedClient != null) ...[
                        _buildBranchSelector(),
                        if (_selectedBranch != null) ...[
                          const SizedBox(height: 12),
                          const Text('Correo de la Sucursal',
                              style: TextStyle(
                                  fontSize: 16, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 10),
                          TextField(
                            controller: _branchEmailCtrl,
                            keyboardType: TextInputType.emailAddress,
                            onChanged: (_) => _saveDraft(),
                            decoration: InputDecoration(
                              hintText: 'ejemplo@correo.com',
                              fillColor: const Color(0xFFF1FBFE),
                              filled: true,
                              prefixIcon: const Icon(Icons.email,
                                  color: Colors.lightBlue),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide.none,
                              ),
                            ),
                          ),
                        ],
                      ],

                      const SizedBox(height: 24),

                      // ── Productos ───────────────────────────────────────────
                      const Text('Productos',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 12),

                      // ── Selector de categorías ──
                      if (_categories.isNotEmpty)
                        SizedBox(
                          height: 40,
                          child: ListView(
                            scrollDirection: Axis.horizontal,
                            children: [
                              Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: ChoiceChip(
                                  label: const Text('Todos'),
                                  selected: _selectedCategoryId == null,
                                  onSelected: (selected) {
                                    setState(() => _selectedCategoryId = null);
                                  },
                                  selectedColor: Colors.lightBlue[100],
                                  labelStyle: TextStyle(
                                    color: _selectedCategoryId == null
                                        ? Colors.blue[800]
                                        : Colors.black,
                                    fontWeight: _selectedCategoryId == null
                                        ? FontWeight.bold
                                        : FontWeight.normal,
                                  ),
                                ),
                              ),
                              ..._categories.map((cat) {
                                return Padding(
                                  padding: const EdgeInsets.only(right: 8),
                                  child: ChoiceChip(
                                    label: Text(cat.name),
                                    selected: _selectedCategoryId == cat.id,
                                    onSelected: (selected) {
                                      setState(() => _selectedCategoryId =
                                          selected ? cat.id : null);
                                    },
                                    selectedColor: Colors.lightBlue[100],
                                    labelStyle: TextStyle(
                                      color: _selectedCategoryId == cat.id
                                          ? Colors.blue[800]
                                          : Colors.black,
                                      fontWeight: _selectedCategoryId == cat.id
                                          ? FontWeight.bold
                                          : FontWeight.normal,
                                    ),
                                  ),
                                );
                              }),
                            ],
                          ),
                        ),

                      const Divider(),

                      ..._products
                          .where((p) =>
                              _selectedCategoryId == null ||
                              p.categoryId == _selectedCategoryId)
                          .map((product) {
                        final qty = _cart[product.id] ?? 0;
                        return Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.grey[100]!),
                          ),
                          child: ListTile(
                            title: Text(product.name,
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold)),
                            subtitle: product.discountPercentage > 0
                                ? Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const SizedBox(height: 4),
                                      Row(
                                        children: [
                                          Text(
                                            '\$${product.price.toStringAsFixed(2)}',
                                            style: const TextStyle(
                                              decoration: TextDecoration.lineThrough,
                                              color: Colors.grey,
                                              fontSize: 12,
                                            ),
                                          ),
                                          const SizedBox(width: 6),
                                          Text(
                                            '\$${(product.price * (1 - product.discountPercentage / 100)).toStringAsFixed(2)}',
                                            style: const TextStyle(
                                              color: Colors.lightBlue,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14,
                                            ),
                                          ),
                                          const SizedBox(width: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                            decoration: BoxDecoration(
                                              color: Colors.amber[100],
                                              borderRadius: BorderRadius.circular(4),
                                            ),
                                            child: Text(
                                              '-${product.discountPercentage.toStringAsFixed(1)}%',
                                              style: TextStyle(
                                                color: Colors.amber[800],
                                                fontWeight: FontWeight.bold,
                                                fontSize: 9,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 2),
                                      Text('Stock: ${product.stock}', style: const TextStyle(fontSize: 12)),
                                    ],
                                  )
                                : Text(
                                    '\$${product.price.toStringAsFixed(2)} - Stock: ${product.stock}'),
                            trailing: _buildQuantitySelector(product, qty),
                          ),
                        );
                      }),

                      const SizedBox(height: 24),

                      // ── Forma de pago ───────────────────────────────────────
                      const Text('Forma de Pago',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        decoration: InputDecoration(
                          fillColor: const Color(0xFFF1FBFE),
                          filled: true,
                          prefixIcon: const Icon(Icons.payment,
                              color: Colors.lightBlue),
                          border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide.none),
                        ),
                        value: _paymentMethod,
                        items: [
                          'Efectivo',
                          'Transferencia',
                          'Cheque',
                          'Crédito'
                        ]
                            .map((m) =>
                                DropdownMenuItem(value: m, child: Text(m)))
                            .toList(),
                        onChanged: (v) {
                          setState(() => _paymentMethod = v!);
                          _saveDraft();
                        },
                      ),

                      const SizedBox(height: 10),
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Factura Directa',
                            style: TextStyle(
                                fontSize: 16, fontWeight: FontWeight.bold)),
                        subtitle: const Text(
                            'Genera factura directa en lugar de prefactura'),
                        value: _isDirectInvoice,
                        activeColor: Colors.lightBlue,
                        onChanged: (bool value) {
                          setState(() => _isDirectInvoice = value);
                          _saveDraft();
                        },
                      ),

                      if (_paymentMethod == 'Crédito') ...[
                        const SizedBox(height: 16),
                        const Text('Días de Crédito',
                            style: TextStyle(
                                fontSize: 16, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _creditDaysCtrl,
                          keyboardType: TextInputType.number,
                          onChanged: (_) => _saveDraft(),
                          decoration: InputDecoration(
                            hintText: 'Ej: 30',
                            fillColor: const Color(0xFFF1FBFE),
                            filled: true,
                            prefixIcon: const Icon(Icons.calendar_today,
                                color: Colors.lightBlue),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide.none,
                            ),
                          ),
                        ),
                      ],

                      const SizedBox(height: 24),

                      // ── Dirección ───────────────────────────────────────────
                      const Text('Dirección de Entrega',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _addressCtrl,
                        onChanged: (_) => _saveDraft(),
                        decoration: InputDecoration(
                          hintText: 'Ej: Calle Principal 123...',
                          fillColor: const Color(0xFFF1FBFE),
                          filled: true,
                          prefixIcon: const Icon(Icons.location_on,
                              color: Colors.lightBlue),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // ── Observaciones ───────────────────────────────────────
                      const Text('Observaciones Adicionales',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _notesCtrl,
                        maxLines: 3,
                        onChanged: (_) => _saveDraft(),
                        decoration: InputDecoration(
                          hintText: 'Escribe aquí cualquier nota importante...',
                          fillColor: const Color(0xFFF1FBFE),
                          filled: true,
                          prefixIcon:
                              const Icon(Icons.notes, color: Colors.lightBlue),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // ── Fecha de entrega ────────────────────────────────────
                      const Text('Fecha de Entrega',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 10),
                      InkWell(
                        onTap: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate: _deliveryDate,
                            firstDate: DateTime.now(),
                            lastDate:
                                DateTime.now().add(const Duration(days: 30)),
                          );
                          if (picked != null) {
                            setState(() => _deliveryDate = picked);
                            _saveDraft();
                          }
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 15),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1FBFE),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.calendar_today,
                                  color: Colors.lightBlue),
                              const SizedBox(width: 12),
                              Text(
                                '${_deliveryDate.day}/${_deliveryDate.month}/${_deliveryDate.year}',
                                style: const TextStyle(fontSize: 16),
                              ),
                            ],
                          ),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // ── Mapa ────────────────────────────────────────────────
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Ubicación en Mapa',
                              style: TextStyle(
                                  fontSize: 16, fontWeight: FontWeight.bold)),
                          TextButton.icon(
                            onPressed: _getCurrentLocation,
                            icon: const Icon(Icons.my_location, size: 18),
                            label: const Text('Actualizar ubicación',
                                style: TextStyle(fontSize: 12)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 5),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: SizedBox(
                          height: 200,
                          child: GoogleMap(
                            initialCameraPosition: CameraPosition(
                              target: LatLng(_currentPosition?.latitude ?? 0,
                                  _currentPosition?.longitude ?? 0),
                              zoom: 15,
                            ),
                            myLocationEnabled: true,
                            onMapCreated: (controller) =>
                                _mapController = controller,
                            markers: _currentPosition == null
                                ? {}
                                : {
                                    Marker(
                                      markerId: const MarkerId('current'),
                                      position: LatLng(
                                          _currentPosition!.latitude,
                                          _currentPosition!.longitude),
                                    )
                                  },
                          ),
                        ),
                      ),
                      const SizedBox(height: 30),
                    ],
                  ),
                ),

                // ── Barra inferior ──────────────────────────────────────────
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                          color: Colors.black.withOpacity(0.05),
                          blurRadius: 10,
                          offset: const Offset(0, -5))
                    ],
                  ),
                  child: SafeArea(
                    top: false,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Subtotal:',
                                style: TextStyle(color: Colors.grey)),
                            Text('\$${_calculateSubtotal().toStringAsFixed(2)}',
                                style:
                                    const TextStyle(fontWeight: FontWeight.bold)),
                          ],
                        ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('IVA:',
                                style: TextStyle(color: Colors.grey)),
                            Text('\$${_calculateIVA().toStringAsFixed(2)}',
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Colors.green)),
                          ],
                        ),
                        const Divider(height: 20),
                        Row(
                          children: [
                            Expanded(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('TOTAL',
                                      style: TextStyle(
                                          fontSize: 12, color: Colors.grey)),
                                  Text(
                                      '\$${(_calculateSubtotal() + _calculateIVA()).toStringAsFixed(2)}',
                                      style: const TextStyle(
                                          fontSize: 20,
                                          fontWeight: FontWeight.bold,
                                          color: Colors.lightBlue)),
                                ],
                              ),
                            ),
                            ElevatedButton(
                              onPressed: _goToReview,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF03A9F4),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 40, vertical: 15),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12)),
                              ),
                              child: const FittedBox(
                                fit: BoxFit.scaleDown,
                                child: Text('Revisar Pedido',
                                    style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.bold)),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  // ─────────────────────────────────────────────────────────────────────────────
  //  WIDGETS AUXILIARES
  // ─────────────────────────────────────────────────────────────────────────────

  Widget _buildClientSearch() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Campo de búsqueda
        TextField(
          controller: _clientSearchCtrl,
          readOnly: _selectedClient != null,
          onChanged: _onSearchChanged,
          decoration: InputDecoration(
            hintText: 'Buscar por nombre o identificación...',
            fillColor: const Color(0xFFF1FBFE),
            filled: true,
            prefixIcon: const Icon(Icons.search, color: Colors.lightBlue),
            suffixIcon: _selectedClient != null
                ? IconButton(
                    icon: const Icon(Icons.close, color: Colors.grey),
                    onPressed: _clearClient,
                    tooltip: 'Cambiar cliente',
                  )
                : _isSearching
                    ? const Padding(
                        padding: EdgeInsets.all(12),
                        child: SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : null,
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none),
          ),
        ),

        // Resultados de búsqueda
        if (_searchResults.isNotEmpty)
          Container(
            margin: const EdgeInsets.only(top: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              boxShadow: [
                BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 8,
                    offset: const Offset(0, 4))
              ],
              border: Border.all(color: Colors.grey[200]!),
            ),
            child: Column(
              children: _searchResults.map((client) {
                return InkWell(
                  onTap: () => _selectClient(client),
                  borderRadius: BorderRadius.circular(12),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 12),
                    child: Row(
                      children: [
                        const Icon(Icons.person_outline,
                            color: Colors.lightBlue, size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(client.nombreLocal != null && client.nombreLocal!.isNotEmpty
                                  ? client.nombreLocal!
                                  : (client.companyName != null && client.companyName!.isNotEmpty
                                      ? client.companyName!
                                      : client.name),
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 14)),
                              if (client.identification != null &&
                                  client.identification!.isNotEmpty)
                                Text(client.identification!,
                                    style: TextStyle(
                                        fontSize: 12, color: Colors.grey[600])),
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios,
                            size: 14, color: Colors.grey),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
          ),

        // Cliente seleccionado (chip)
        if (_selectedClient != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFE0F7FA),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFF03A9F4)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.check_circle,
                      color: Color(0xFF03A9F4), size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(_selectedClient!.nombreLocal != null && _selectedClient!.nombreLocal!.isNotEmpty
                        ? _selectedClient!.nombreLocal!
                        : (_selectedClient!.companyName != null && _selectedClient!.companyName!.isNotEmpty
                            ? _selectedClient!.companyName!
                            : _selectedClient!.name),
                        style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF0277BD))),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  void _showBranchSearchDialog() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (BuildContext context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.7,
          minChildSize: 0.5,
          maxChildSize: 0.95,
          builder: (_, controller) {
            return StatefulBuilder(
              builder: (BuildContext context, StateSetter setModalState) {
                final filtered = _branches
                    .where((b) => b.name
                        .toLowerCase()
                        .contains(_branchSearchQuery.toLowerCase()))
                    .toList();

                return Container(
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(20),
                      topRight: Radius.circular(20),
                    ),
                  ),
                  child: Column(
                    children: [
                      const SizedBox(height: 10),
                      Container(
                        width: 50,
                        height: 5,
                        decoration: BoxDecoration(
                          color: Colors.grey[300],
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      const SizedBox(height: 15),
                      const Text(
                        'Seleccionar Sucursal',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 15),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Buscar sucursal...',
                            prefixIcon: const Icon(Icons.search, color: Colors.lightBlue),
                            fillColor: const Color(0xFFF1FBFE),
                            filled: true,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide.none,
                            ),
                          ),
                          onChanged: (value) {
                            setModalState(() {
                              _branchSearchQuery = value;
                            });
                          },
                        ),
                      ),
                      const SizedBox(height: 10),
                      Expanded(
                        child: ListView(
                          controller: controller,
                          children: [
                            ListTile(
                              leading: const Icon(Icons.store_mall_directory_outlined, color: Colors.grey),
                              title: const Text('Sin sucursal específica', style: TextStyle(color: Colors.grey)),
                              onTap: () {
                                setState(() {
                                  _selectedBranch = null;
                                  _branchEmailCtrl.clear();
                                });
                                _saveDraft();
                                Navigator.pop(context);
                              },
                            ),
                            ...filtered.map((b) {
                              return ListTile(
                                leading: const Icon(Icons.store, color: Colors.lightBlue),
                                title: Text(b.name),
                                subtitle: Text(b.address ?? 'Sin dirección'),
                                selected: _selectedBranch?.id == b.id,
                                onTap: () {
                                  setState(() {
                                    _selectedBranch = b;
                                    if (b.email != null && b.email!.isNotEmpty) {
                                      _branchEmailCtrl.text = b.email!;
                                    }
                                  });
                                  _saveDraft();
                                  Navigator.pop(context);
                                },
                              );
                            }),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            );
          },
        );
      },
    );
  }

  Widget _buildBranchSelector() {
    if (_loadingBranches) {
      return const Padding(
        padding: EdgeInsets.only(bottom: 16),
        child: Row(
          children: [
            SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2)),
            SizedBox(width: 10),
            Text('Cargando sucursales...',
                style: TextStyle(color: Colors.grey)),
          ],
        ),
      );
    }

    if (_branches.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Sucursal',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 10),
          InkWell(
            onTap: () {
              setState(() {
                _branchSearchQuery = '';
              });
              _showBranchSearchDialog();
            },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: const Color(0xFFF1FBFE),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(Icons.store, color: Colors.lightBlue),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      _selectedBranch != null
                          ? _selectedBranch!.name
                          : 'Sin sucursal específica',
                      style: TextStyle(
                        fontSize: 16,
                        color: _selectedBranch != null
                            ? Colors.black87
                            : Colors.grey,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Icon(Icons.arrow_drop_down, color: Colors.grey),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  double _calculateSubtotal() {
    double subtotal = 0;
    _cart.forEach((productId, qty) {
      if (qty > 0) {
        final product = _products.firstWhere((p) => p.id == productId);
        final double discountedPrice = product.price * (1 - product.discountPercentage / 100);
        subtotal += discountedPrice * qty;
      }
    });
    return subtotal;
  }

  double _calculateIVA() {
    double iva = 0;
    _cart.forEach((productId, qty) {
      if (qty > 0) {
        final product = _products.firstWhere((p) => p.id == productId);
        final double discountedPrice = product.price * (1 - product.discountPercentage / 100);
        iva += (discountedPrice * qty) * (product.taxPercentage / 100);
      }
    });
    return iva;
  }

  Widget _buildQuantitySelector(Product product, int qty) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          icon:
              const Icon(Icons.remove_circle_outline, color: Colors.lightBlue),
          onPressed: qty > 0
              ? () {
                  setState(() => _cart[product.id] = qty - 1);
                  _saveDraft();
                }
              : null,
        ),
        GestureDetector(
          onTap: () => _showQuantityDialog(product),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFFF1FBFE),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.lightBlue[100]!),
            ),
            child: Text(
              '$qty',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ),
        ),
        IconButton(
          icon: const Icon(Icons.add_circle_outline, color: Colors.lightBlue),
          onPressed: () {
            setState(() => _cart[product.id] = qty + 1);
            _saveDraft();
          },
        ),
      ],
    );
  }

  void _showQuantityDialog(Product product) {
    final TextEditingController qtyCtrl =
        TextEditingController(text: (_cart[product.id] ?? 0).toString());
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(product.name),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Ingresa la cantidad deseada:'),
            const SizedBox(height: 12),
            TextField(
              controller: qtyCtrl,
              keyboardType: TextInputType.number,
              autofocus: true,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                hintText: 'Ej: 250',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () {
              final newQty = int.tryParse(qtyCtrl.text) ?? 0;
              setState(() => _cart[product.id] = newQty);
              _saveDraft();
              Navigator.pop(context);
            },
            child: const Text('Aceptar'),
          ),
        ],
      ),
    );
  }
}
