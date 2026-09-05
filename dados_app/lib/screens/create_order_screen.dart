import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'dart:convert';
import '../services/api_service.dart';
import '../services/offline_database_service.dart';
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
  final TextEditingController _creditDaysCtrl =
      TextEditingController(text: '30');
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
      final categories = (data as List).map((j) => Category.fromJson(j)).toList();
      setState(() => _categories = categories);
      await OfflineDatabaseService.instance.saveCategories(categories);
    } catch (e) {
      debugPrint('Error o sin red al cargar categorías. Cargando localmente: $e');
      final localCats = await OfflineDatabaseService.instance.getCategoriesLocal();
      if (mounted && localCats.isNotEmpty) {
        setState(() => _categories = localCats);
      }
    }
  }

  // ── Carga únicamente productos (no clientes)
  Future<void> _loadProducts({int? clientId}) async {
    final api = context.read<ApiService>();
    try {
      final String endpoint =
          clientId != null ? '/products?client_id=$clientId' : '/products';
      final productsData = await api.get(endpoint);
      if (!mounted) return;
      final prods = (productsData as List).map((j) => Product.fromJson(j)).toList();
      setState(() => _products = prods);
      await OfflineDatabaseService.instance.saveProducts(prods);
    } catch (e) {
      debugPrint('Error o sin red al cargar productos. Cargando localmente: $e');
      final localProds = await OfflineDatabaseService.instance.getProductsLocal();
      if (mounted && localProds.isNotEmpty) {
        setState(() => _products = localProds);
      }
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
        final clients = (data as List).map((j) => Client.fromJson(j)).toList();
        setState(() => _searchResults = clients);
        await OfflineDatabaseService.instance.saveClients(clients);
      } catch (e) {
        debugPrint('Búsqueda remota falló (offline). Buscando localmente: $e');
        final localClients = await OfflineDatabaseService.instance.searchClientsLocal(value.trim());
        if (mounted) {
          setState(() => _searchResults = localClients);
        }
      } finally {
        if (mounted) setState(() => _isSearching = false);
      }
    });
  }

  ClientCreditStatus? _creditStatus;
  bool _loadingCreditStatus = false;

  // ── Selección de cliente → carga sus sucursales y estado de crédito desde Contifico
  Future<void> _selectClient(Client client) async {
    String displayName =
        client.nombreLocal != null && client.nombreLocal!.isNotEmpty
            ? client.nombreLocal!
            : (client.companyName != null && client.companyName!.isNotEmpty
                ? client.companyName!
                : client.name);

    final bool initialHasCredit = (client.creditLimit != null && client.creditLimit! > 0) ||
                                  (client.creditDays != null && client.creditDays! > 0);

    setState(() {
      _selectedClient = client;
      _searchResults = [];
      _clientSearchCtrl.text = displayName;
      _branches = [];
      _selectedBranch = null;
      _loadingBranches = true;
      _creditStatus = null;
      _loadingCreditStatus = true;
      if (initialHasCredit) {
        _paymentMethod = 'Crédito';
        _creditDaysCtrl.text = (client.creditDays != null && client.creditDays! > 0)
            ? client.creditDays.toString()
            : '30';
      } else {
        _paymentMethod = 'Efectivo';
        _creditDaysCtrl.text = '0';
      }
    });
    _saveDraft();

    try {
      final api = context.read<ApiService>();
      try {
        final data = await api.get('/clients/${client.id}/branches');
        if (mounted) {
          final branches = (data as List).map((j) => Branch.fromJson(j)).toList();
          setState(() => _branches = branches);
          await OfflineDatabaseService.instance.saveBranches(client.id, branches);
        }
      } catch (e) {
        debugPrint('Sucursales falló online. Cargando localmente: $e');
        final localBranches = await OfflineDatabaseService.instance.getBranchesLocal(client.id);
        if (mounted) setState(() => _branches = localBranches);
      }

      await _loadProducts(clientId: client.id);

      // Cargar facturas pendientes y límite de crédito desde Contifico a través del backend
      try {
        final statusJson = await api.getClientCreditStatus(client.id);
        if (mounted) {
          final creditStatus = ClientCreditStatus.fromJson(statusJson);
          final bool hasCreditConfigured = creditStatus.hasCreditConfigured ||
              (creditStatus.creditLimit != null && creditStatus.creditLimit! > 0) ||
              (creditStatus.creditDays != null && creditStatus.creditDays! > 0);

          setState(() {
            _creditStatus = creditStatus;
            _loadingCreditStatus = false;
            if (hasCreditConfigured) {
              _paymentMethod = 'Crédito';
              if (creditStatus.creditDays != null && creditStatus.creditDays! > 0) {
                _creditDaysCtrl.text = creditStatus.creditDays.toString();
              }
            } else {
              _paymentMethod = 'Efectivo';
              _creditDaysCtrl.text = '0';
            }
          });
          _saveDraft();
          _checkAndShowCreditDialog(creditStatus);
        }
      } catch (e) {
        debugPrint('No se pudo consultar estado de crédito en vivo (Modo Offline): $e');
        if (mounted) {
          final offlineStatus = ClientCreditStatus(
            clientId: client.id,
            identification: client.identification,
            creditLimit: client.creditLimit,
            creditDays: client.creditDays,
            hasCreditConfigured: (client.creditLimit != null && client.creditLimit! > 0) || (client.creditDays != null && client.creditDays! > 0),
            totalPendiente: 0.0,
            totalVencido: 0.0,
            countPendientes: 0,
            countVencidos: 0,
            availableCredit: client.creditLimit,
            documents: [],
          );
          setState(() {
            _creditStatus = offlineStatus;
            _loadingCreditStatus = false;
            if (initialHasCredit) {
              _paymentMethod = 'Crédito';
            } else {
              _paymentMethod = 'Efectivo';
              _creditDaysCtrl.text = '0';
            }
          });
          _saveDraft();
          _checkAndShowCreditDialog(offlineStatus);
        }
      }
    } catch (e) {
      debugPrint('Error cargando sucursales/crédito: $e');
    } finally {
      if (mounted) {
        setState(() {
          _loadingBranches = false;
          _loadingCreditStatus = false;
        });
      }
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
      _creditStatus = null;
      _loadingCreditStatus = false;
      _paymentMethod = 'Efectivo';
      _creditDaysCtrl.text = '0';
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
          const SnackBar(
              content: Text('No se pudo obtener la ubicación precisa')),
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
                      if (_selectedClient != null) _buildCreditSummaryCard(),
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
                          child: Material(
                            color: Colors.white,
                            clipBehavior: Clip.antiAlias,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(color: Colors.grey[100]!),
                            ),
                            child: ListTile(
                              title: Text(product.name,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.bold)),
                              subtitle: product.discountPercentage > 0
                                  ? Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        const SizedBox(height: 4),
                                        Row(
                                          children: [
                                            Text(
                                              '\$${product.price.toStringAsFixed(2)}',
                                              style: const TextStyle(
                                                decoration:
                                                    TextDecoration.lineThrough,
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
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                      horizontal: 4,
                                                      vertical: 1),
                                              decoration: BoxDecoration(
                                                color: Colors.amber[100],
                                                borderRadius:
                                                    BorderRadius.circular(4),
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
                                        Text('Stock: ${product.stock}',
                                            style:
                                                const TextStyle(fontSize: 12)),
                                      ],
                                    )
                                  : Text(
                                      '\$${product.price.toStringAsFixed(2)} - Stock: ${product.stock}'),
                              trailing: _buildQuantitySelector(product, qty),
                            ),
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
                          if (v == 'Crédito') {
                            _checkCreditWarningOnPaymentMethod();
                          }
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
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold)),
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
            child: Material(
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              elevation: 2,
              shadowColor: Colors.black.withOpacity(0.4),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: Colors.grey[200]!),
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
                                Text(
                                    client.nombreLocal != null &&
                                            client.nombreLocal!.isNotEmpty
                                        ? client.nombreLocal!
                                        : (client.companyName != null &&
                                                client.companyName!.isNotEmpty
                                            ? client.companyName!
                                            : client.name),
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w600,
                                        fontSize: 14)),
                                if (client.identification != null &&
                                    client.identification!.isNotEmpty)
                                  Text(client.identification!,
                                      style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey[600])),
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
                    child: Text(
                        _selectedClient!.nombreLocal != null &&
                                _selectedClient!.nombreLocal!.isNotEmpty
                            ? _selectedClient!.nombreLocal!
                            : (_selectedClient!.companyName != null &&
                                    _selectedClient!.companyName!.isNotEmpty
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
                            prefixIcon: const Icon(Icons.search,
                                color: Colors.lightBlue),
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
                              leading: const Icon(
                                  Icons.store_mall_directory_outlined,
                                  color: Colors.grey),
                              title: const Text('Sin sucursal específica',
                                  style: TextStyle(color: Colors.grey)),
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
                                leading: const Icon(Icons.store,
                                    color: Colors.lightBlue),
                                title: Text(b.name),
                                subtitle: Text(b.address ?? 'Sin dirección'),
                                selected: _selectedBranch?.id == b.id,
                                onTap: () {
                                  setState(() {
                                    _selectedBranch = b;
                                    if (b.email != null &&
                                        b.email!.isNotEmpty) {
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
        final double discountedPrice =
            product.price * (1 - product.discountPercentage / 100);
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
        final double discountedPrice =
            product.price * (1 - product.discountPercentage / 100);
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

  void _checkAndShowCreditDialog(ClientCreditStatus status) {
    if (!mounted) return;

    final bool hasOverdue = status.hasOverdueInvoices;
    final bool isExhausted = status.isCreditExhausted;
    final bool isLow = status.isLowCredit;
    final bool noConfig = !status.hasCreditConfigured;

    if (hasOverdue || isExhausted || isLow || noConfig) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: [
              Icon(
                hasOverdue || isExhausted
                    ? Icons.warning_amber_rounded
                    : Icons.info_outline,
                color: hasOverdue || isExhausted ? Colors.red : Colors.orange,
                size: 28,
              ),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Estado de Crédito del Cliente',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (noConfig)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.amber.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.amber.shade300),
                    ),
                    child: const Text(
                      '⚠️ El cliente NO tiene días de crédito ni monto asignado.',
                      style: TextStyle(color: Colors.amber, fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                if (hasOverdue)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade300),
                    ),
                    child: Text(
                      '🚨 Facturas Vencidas: ${status.countVencidos} factura(s) por un total de \$${status.totalVencido.toStringAsFixed(2)}.',
                      style: TextStyle(color: Colors.red.shade800, fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                if (isExhausted)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.shade300),
                    ),
                    child: Text(
                      '⛔ Cupo Agotado: Total pendiente (\$${status.totalPendiente.toStringAsFixed(2)}) supera o iguala el cupo.',
                      style: TextStyle(color: Colors.red.shade800, fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                if (isLow && !isExhausted)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.orange.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.orange.shade300),
                    ),
                    child: Text(
                      '⚠️ Poco Cupo Disponible: Le quedan \$${status.availableCredit?.toStringAsFixed(2)} de \$${status.creditLimit?.toStringAsFixed(2)}.',
                      style: TextStyle(color: Colors.orange.shade900, fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                const Divider(),
                _buildCreditDetailRow('Días de crédito:', status.creditDays != null && status.creditDays! > 0 ? '${status.creditDays} días' : 'Sin asignación'),
                _buildCreditDetailRow('Monto límite (Cupo):', status.creditLimit != null && status.creditLimit! > 0 ? '\$${status.creditLimit!.toStringAsFixed(2)}' : 'Sin asignación'),
                _buildCreditDetailRow('Total Pendiente:', '\$${status.totalPendiente.toStringAsFixed(2)} (${status.countPendientes} facturas)'),
                _buildCreditDetailRow('Total Vencido:', '\$${status.totalVencido.toStringAsFixed(2)} (${status.countVencidos} facturas)', isWarning: hasOverdue),
                if (status.creditLimit != null && status.creditLimit! > 0)
                  _buildCreditDetailRow('Cupo Disponible:', '\$${(status.availableCredit ?? 0.0).toStringAsFixed(2)}', isBold: true),
              ],
            ),
          ),
          actions: [
            ElevatedButton(
              onPressed: () => Navigator.of(ctx).pop(),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF003366),
                foregroundColor: Colors.white,
              ),
              child: const Text('Entendido'),
            ),
          ],
        ),
      );
    }
  }

  Widget _buildCreditDetailRow(String label, String value, {bool isWarning = false, bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: Colors.grey[700], fontSize: 13)),
          Text(
            value,
            style: TextStyle(
              fontWeight: isBold || isWarning ? FontWeight.bold : FontWeight.normal,
              color: isWarning ? Colors.red : (isBold ? Colors.green[800] : Colors.black87),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCreditSummaryCard() {
    if (_loadingCreditStatus) {
      return Container(
        margin: const EdgeInsets.only(top: 8, bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFF1FBFE),
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Row(
          children: [
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            SizedBox(width: 12),
            Text(
              'Consultando estado de crédito en Contifico...',
              style: TextStyle(fontSize: 13, color: Colors.grey),
            ),
          ],
        ),
      );
    }

    final status = _creditStatus;
    if (status == null) return const SizedBox.shrink();

    final hasOverdue = status.hasOverdueInvoices;
    final isExhausted = status.isCreditExhausted;
    final isLow = status.isLowCredit;
    final noConfig = !status.hasCreditConfigured;

    Color cardColor = Colors.green.shade50;
    Color borderColor = Colors.green.shade200;
    IconData icon = Icons.check_circle_outline;
    Color iconColor = Colors.green.shade700;

    if (hasOverdue || isExhausted) {
      cardColor = Colors.red.shade50;
      borderColor = Colors.red.shade200;
      icon = Icons.warning_amber_rounded;
      iconColor = Colors.red.shade700;
    } else if (isLow || noConfig) {
      cardColor = Colors.amber.shade50;
      borderColor = Colors.amber.shade300;
      icon = Icons.info_outline;
      iconColor = Colors.amber.shade900;
    }

    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: iconColor, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  noConfig
                      ? 'Cliente sin días/monto de crédito asignado'
                      : (hasOverdue
                          ? 'Facturas vencidas detectadas'
                          : (isExhausted
                              ? 'Cupo de crédito agotado'
                              : (isLow ? 'Poco cupo disponible' : 'Crédito al día'))),
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: iconColor,
                    fontSize: 13,
                  ),
                ),
              ),
              InkWell(
                onTap: () => _checkAndShowCreditDialog(status),
                child: Padding(
                  padding: const EdgeInsets.all(4.0),
                  child: Text(
                    'Ver detalle',
                    style: TextStyle(
                      color: Colors.blue.shade800,
                      fontWeight: FontWeight.bold,
                      fontSize: 12,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Días crédito: ${status.creditDays != null && status.creditDays! > 0 ? "${status.creditDays}d" : "0d"}',
                style: const TextStyle(fontSize: 12, color: Colors.black87),
              ),
              Text(
                'Pendiente: \$${status.totalPendiente.toStringAsFixed(2)}',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: hasOverdue ? FontWeight.bold : FontWeight.normal,
                  color: hasOverdue ? Colors.red.shade800 : Colors.black87,
                ),
              ),
              Text(
                'Disponible: ${status.availableCredit != null ? "\$${status.availableCredit!.toStringAsFixed(2)}" : "N/A"}',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                  color: isExhausted ? Colors.red.shade800 : Colors.green.shade900,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _checkCreditWarningOnPaymentMethod() {
    final client = _selectedClient;
    if (client == null) return;

    final noLimit = client.creditLimit == null || client.creditLimit! <= 0;
    final noDays = client.creditDays == null || client.creditDays! <= 0;

    if (noLimit || noDays) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          duration: const Duration(seconds: 4),
          backgroundColor: Colors.amber.shade900,
          content: const Row(
            children: [
              Icon(Icons.warning_amber_rounded, color: Colors.white),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'El cliente NO tiene días de crédito ni monto asignado.',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
        ),
      );
    }
  }
}
