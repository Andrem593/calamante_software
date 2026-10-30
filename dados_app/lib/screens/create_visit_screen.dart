import 'dart:async';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';
import '../models/models.dart';
import '../services/api_service.dart';
import '../services/offline_database_service.dart';
import '../services/sync_service.dart';

class CreateVisitScreen extends StatefulWidget {
  const CreateVisitScreen({super.key});

  @override
  State<CreateVisitScreen> createState() => _CreateVisitScreenState();
}

class _CreateVisitScreenState extends State<CreateVisitScreen> {
  final TextEditingController _clientSearchCtrl = TextEditingController();
  final TextEditingController _commentCtrl = TextEditingController();

  Client? _selectedClient;
  List<Client> _searchResults = [];
  bool _isSearching = false;
  Timer? _debounce;

  Position? _currentPosition;
  GoogleMapController? _mapController;
  bool _loadingLocation = false;
  bool _submitting = false;
  bool _isQuickCensusing = false;
  bool _hasQuickCensused = false;

  List<dynamic> _equipments = [];
  List<int> _selectedEquipmentIds = [];
  bool _loadingEquipments = false;

  List<Branch> _branches = [];
  Branch? _selectedBranch;
  bool _loadingBranches = false;
  String _branchSearchQuery = '';

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
    _loadEquipments();
  }

  @override
  void dispose() {
    _clientSearchCtrl.dispose();
    _commentCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _getCurrentLocation() async {
    setState(() => _loadingLocation = true);
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
                content: Text(
                    'Los servicios de ubicación están desactivados. Por favor actívalos.')),
          );
        }
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Permiso de ubicación denegado.')),
            );
          }
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
                content: Text(
                    'Los permisos de ubicación están denegados permanentemente.')),
          );
        }
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        timeLimit: const Duration(seconds: 10),
        desiredAccuracy: LocationAccuracy.high,
      );

      setState(() {
        _currentPosition = pos;
      });
      debugPrint('CreateVisitScreen: Ubicación obtenida: $_currentPosition');

      if (mounted && _mapController != null) {
        _mapController!.animateCamera(
          CameraUpdate.newLatLngZoom(
            LatLng(pos.latitude, pos.longitude),
            15,
          ),
        );
      }
    } catch (e) {
      debugPrint('CreateVisitScreen: Error en Geolocator: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text(
                  'No se pudo obtener la ubicación precisa del dispositivo.')),
        );
      }
    } finally {
      if (mounted) setState(() => _loadingLocation = false);
    }
  }

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

  Future<void> _loadClientEquipments(int clientId, {int? branchId}) async {
    final api = context.read<ApiService>();
    setState(() {
      _loadingEquipments = true;
      _selectedEquipmentIds = [];
    });
    try {
      final data = await api.getClientEquipments(clientId, branchId: branchId);
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

  Future<void> _loadBranches(Client client) async {
    final api = context.read<ApiService>();
    setState(() {
      _branches = [];
      _selectedBranch = null;
      _loadingBranches = true;
    });
    try {
      final data = await api.get('/clients/${client.id}/branches');
      if (!mounted) return;
      setState(() {
        _branches = (data as List).map((j) => Branch.fromJson(j)).toList();
      });
    } catch (e) {
      debugPrint('Error cargando sucursales: $e');
    } finally {
      if (mounted) setState(() => _loadingBranches = false);
    }
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
                                color: Color(0xFF03A9F4)),
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
                                });
                                _loadClientEquipments(_selectedClient!.id, branchId: null);
                                Navigator.pop(context);
                              },
                            ),
                            ...filtered.map((b) {
                              return ListTile(
                                leading: const Icon(Icons.store,
                                    color: Color(0xFF03A9F4)),
                                title: Text(b.name),
                                subtitle: Text(b.address ?? 'Sin dirección'),
                                selected: _selectedBranch?.id == b.id,
                                onTap: () {
                                  setState(() {
                                    _selectedBranch = b;
                                  });
                                  _loadClientEquipments(_selectedClient!.id, branchId: b.id);
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
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
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
                  const Icon(Icons.store, color: Color(0xFF03A9F4)),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      _selectedBranch != null
                          ? _selectedBranch!.name
                          : 'Sin sucursal específica',
                      style: TextStyle(
                        fontSize: 14,
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
      // Esperar hasta que termine la carga
      int attempts = 0;
      while (_loadingEquipments && attempts < 30) {
        await Future.delayed(const Duration(milliseconds: 100));
        attempts++;
      }
      if (!mounted) return;
      Navigator.of(context).pop(); // cerrar diálogo de espera
    }

    // Si no cargaron (error o aún vacíos), intentar cargar de nuevo
    if (_equipments.isEmpty) {
      await _loadEquipments();
      if (!mounted) return;
    }

    final List<int>? result = await showModalBottomSheet<List<int>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EquipmentPickerModal(
        equipments: List<dynamic>.from(_equipments), // copia defensiva
        initialSelected: List<int>.from(_selectedEquipmentIds),
      ),
    );

    if (result != null && mounted) {
      setState(() => _selectedEquipmentIds = result);
    }
  }

  Widget _buildClientEquipmentsSection() {
    return InkWell(
      onTap: _selectedClient == null ? null : _openEquipmentModal,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        decoration: BoxDecoration(
          color: _selectedClient == null
              ? Colors.grey[50]
              : const Color(0xFFF0F8FF),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: _selectedClient == null
                ? Colors.grey[200]!
                : Colors.lightBlue.shade200,
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: _selectedClient == null
                    ? Colors.grey[100]
                    : Colors.blue[50],
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(Icons.kitchen_outlined,
                  color: _selectedClient == null
                      ? Colors.grey[400]
                      : Colors.blue[700],
                  size: 22),
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
                        color: _selectedClient == null
                            ? Colors.grey[400]
                            : Colors.blue[900],
                      )),
                  const SizedBox(height: 3),
                  _loadingEquipments
                      ? const SizedBox(
                          height: 14,
                          width: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(
                          _selectedClient == null
                              ? 'Selecciona un cliente primero'
                              : _selectedEquipmentIds.isEmpty
                                  ? 'Toca para registrar equipos'
                                  : '${_selectedEquipmentIds.length} equipo${_selectedEquipmentIds.length == 1 ? '' : 's'} registrado${_selectedEquipmentIds.length == 1 ? '' : 's'}',
                          style: TextStyle(
                            fontSize: 12,
                            color: _selectedClient == null
                                ? Colors.grey[400]
                                : _selectedEquipmentIds.isEmpty
                                    ? Colors.grey[500]
                                    : Colors.blue[700],
                          ),
                        ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded,
                color: _selectedClient == null
                    ? Colors.grey[300]
                    : Colors.blue[400]),
          ],
        ),
      ),
    );
  }

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
        debugPrint('Error en búsqueda de clientes: $e');
      } finally {
        if (mounted) setState(() => _isSearching = false);
      }
    });
  }

  void _selectClient(Client client) {
    String displayName =
        client.nombreLocal != null && client.nombreLocal!.isNotEmpty
            ? client.nombreLocal!
            : (client.companyName != null && client.companyName!.isNotEmpty
                ? client.companyName!
                : client.name);

    setState(() {
      _selectedClient = client;
      _searchResults = [];
      _clientSearchCtrl.text = displayName;
    });

    _loadBranches(client);
    _loadClientEquipments(client.id, branchId: null);
  }

  void _clearClient() {
    setState(() {
      _selectedClient = null;
      _clientSearchCtrl.clear();
      _searchResults = [];
      _selectedEquipmentIds = [];
      _branches = [];
      _selectedBranch = null;
    });
  }

  Future<void> _submitVisit() async {
    if (_selectedClient == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Por favor, selecciona un cliente.')),
      );
      return;
    }

    setState(() => _submitting = true);

    final body = {
      'client_id': _selectedClient!.id,
      'branch_id': _selectedBranch?.id,
      'comment': _commentCtrl.text.trim(),
      'latitude': _currentPosition?.latitude,
      'longitude': _currentPosition?.longitude,
    };

    final isOnline = context.read<SyncService>().isOnline;

    if (!isOnline) {
      await OfflineDatabaseService.instance.insertOfflineVisit(
        body,
        clientId: _selectedClient!.id,
        clientName: _selectedClient!.name,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('📱 Visita guardada localmente. Se auto-sincronizará al conectarte.'),
            backgroundColor: Colors.orange,
            duration: Duration(seconds: 4),
          ),
        );
        Navigator.pop(context, true);
      }
      return;
    }

    try {
      final api = context.read<ApiService>();
      await api.post('/visits', body);
      await api.syncClientEquipments(
          _selectedClient!.id, _selectedEquipmentIds, branchId: _selectedBranch?.id);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Visita registrada con éxito.'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      }
    } catch (e) {
      debugPrint('Error registrando visita online. Almacenando localmente: $e');
      await OfflineDatabaseService.instance.insertOfflineVisit(
        body,
        clientId: _selectedClient!.id,
        clientName: _selectedClient!.name,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('📱 Visita guardada localmente por error de conexión.'),
            backgroundColor: Colors.orange,
            duration: Duration(seconds: 4),
          ),
        );
        Navigator.pop(context, true);
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
        title: const Text(
          'Registrar Visita',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // STEP 1: SELECT CLIENT
            const Text(
              'Seleccionar Cliente',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _clientSearchCtrl,
              readOnly: _selectedClient != null,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Buscar por nombre o identificación...',
                fillColor: const Color(0xFFF1FBFE),
                filled: true,
                prefixIcon: const Icon(Icons.search, color: Color(0xFF03A9F4)),
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
                  borderSide: BorderSide.none,
                ),
              ),
            ),

            // Autocomplete Search Results dropdown list
            if (_searchResults.isNotEmpty) ...[
              const SizedBox(height: 4),
              Material(
                color: Colors.white,
                elevation: 2,
                shadowColor: Colors.black.withOpacity(0.4),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(color: Colors.grey[200]!),
                ),
                clipBehavior: Clip.antiAlias,
                child: ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: _searchResults.length,
                  separatorBuilder: (context, index) =>
                      const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final client = _searchResults[index];
                    final name = client.nombreLocal != null &&
                            client.nombreLocal!.isNotEmpty
                        ? client.nombreLocal!
                        : (client.companyName != null &&
                                client.companyName!.isNotEmpty
                            ? client.companyName!
                            : client.name);
                    return ListTile(
                      onTap: () => _selectClient(client),
                      leading: const Icon(Icons.person_outline,
                          color: Color(0xFF03A9F4)),
                      title: Text(
                        name,
                        style: const TextStyle(
                            fontSize: 13, fontWeight: FontWeight.bold),
                      ),
                      subtitle: Text(
                        'RUC/Ced: ${client.identification ?? 'N/A'}',
                        style: const TextStyle(fontSize: 11),
                      ),
                    );
                  },
                ),
              ),
            ],

            if (_selectedClient != null) ...[
              const SizedBox(height: 16),
              _buildBranchSelector(),
              const SizedBox(height: 16),
              _buildClientEquipmentsSection(),
            ],

            const SizedBox(height: 24),

            // STEP 2: COMMENTS
            const Text(
              'Comentarios',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _commentCtrl,
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                hintText: 'Escribe un comentario sobre la visita...',
                fillColor: const Color(0xFFF1FBFE),
                filled: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),

            const SizedBox(height: 24),

            // STEP 3: GPS LOCATION AND MAP
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Ubicación GPS de la Visita',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                ),
                TextButton.icon(
                  onPressed: _getCurrentLocation,
                  icon: const Icon(Icons.my_location, size: 18),
                  label:
                      const Text('Actualizar', style: TextStyle(fontSize: 12)),
                  style: TextButton.styleFrom(
                    foregroundColor: const Color(0xFF03A9F4),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (_loadingLocation && _currentPosition == null)
              Container(
                height: 200,
                decoration: BoxDecoration(
                  color: const Color(0xFFF1FBFE),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Center(
                  child: CircularProgressIndicator(),
                ),
              )
            else
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: SizedBox(
                  height: 200,
                  child: GoogleMap(
                    initialCameraPosition: CameraPosition(
                      target: LatLng(
                        _currentPosition?.latitude ?? -0.1807,
                        _currentPosition?.longitude ?? -78.4678,
                      ),
                      zoom: 15,
                    ),
                    myLocationEnabled: true,
                    onMapCreated: (controller) {
                      _mapController = controller;
                      if (_currentPosition != null) {
                        _mapController!.animateCamera(
                          CameraUpdate.newLatLngZoom(
                            LatLng(_currentPosition!.latitude,
                                _currentPosition!.longitude),
                            15,
                          ),
                        );
                      }
                    },
                    markers: _currentPosition == null
                        ? {}
                        : {
                            Marker(
                              markerId: const MarkerId('visit_location'),
                              position: LatLng(
                                _currentPosition!.latitude,
                                _currentPosition!.longitude,
                              ),
                            )
                          },
                  ),
                ),
              ),
            if (_currentPosition != null) ...[
              const SizedBox(height: 8),
              Text(
                'Lat: ${_currentPosition!.latitude.toStringAsFixed(6)}, Lng: ${_currentPosition!.longitude.toStringAsFixed(6)}',
                style: TextStyle(
                  fontSize: 11,
                  color: Colors.grey[600],
                  fontFamily: 'monospace',
                ),
              ),
            ],

            if (_selectedClient != null) ...[
              _buildQuickCensusWidget(),
            ],

            const SizedBox(height: 40),

            // SUBMIT BUTTON
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: _submitting ? null : _submitVisit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF03A9F4),
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: Colors.grey[300],
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 2,
                ),
                child: _submitting
                    ? const CircularProgressIndicator(
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                      )
                    : const Text(
                        'Registrar Visita',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _quickCensusCurrentLocation() async {
    if (_selectedClient == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Selecciona un cliente primero.'), backgroundColor: Colors.red),
      );
      return;
    }

    if (_currentPosition == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Obteniendo tu posición GPS... Espera unos segundos.'), backgroundColor: Colors.orange),
      );
      await _getCurrentLocation();
      if (!mounted || _currentPosition == null) return;
    }

    setState(() => _isQuickCensusing = true);

    try {
      final syncService = context.read<SyncService>();
      final apiService = context.read<ApiService>();

      final payload = {
        'client_id': _selectedClient!.id,
        if (_selectedBranch != null) 'branch_id': _selectedBranch!.id,
        'latitude': _currentPosition!.latitude,
        'longitude': _currentPosition!.longitude,
      };

      if (syncService.isOnline) {
        await apiService.saveBranchCensus(payload, clientId: _selectedClient!.id);
      } else {
        await OfflineDatabaseService.instance.insertOfflineCensus(
          payload,
          clientId: _selectedClient!.id,
          clientName: _selectedClient!.name,
          branchId: _selectedBranch?.id,
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
                    _selectedBranch != null
                        ? '¡Ubicación de "${_selectedBranch!.name}" actualizada con tu GPS!'
                        : '¡Ubicación de "${_selectedClient!.name}" actualizada con tu GPS!',
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
    final bool isGeocoded = (_selectedBranch?.isGeocoded ?? false) || _hasQuickCensused;

    return Container(
      margin: const EdgeInsets.only(top: 14),
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
                  isGeocoded ? 'Sucursal Georreferenciada' : '¿Estás en el local del cliente?',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: isGeocoded ? Colors.green[900] : Colors.orange[900],
                  ),
                ),
                Text(
                  isGeocoded
                      ? 'Las coordenadas ya están fijadas. Puedes re-fijarlas si estás en su puerta.'
                      : 'Fija esta ubicación GPS actual como la oficial de la sucursal en 1 clic.',
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
