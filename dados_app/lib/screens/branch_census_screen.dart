import 'dart:async';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';
import '../models/models.dart';
import '../services/api_service.dart';
import '../services/offline_database_service.dart';
import '../services/sync_service.dart';

class BranchCensusScreen extends StatefulWidget {
  final Client? initialClient;
  final Branch? initialBranch;

  const BranchCensusScreen({
    super.key,
    this.initialClient,
    this.initialBranch,
  });

  @override
  State<BranchCensusScreen> createState() => _BranchCensusScreenState();
}

class _BranchCensusScreenState extends State<BranchCensusScreen> {
  // Client selection
  final TextEditingController _clientSearchCtrl = TextEditingController();
  Client? _selectedClient;
  List<Client> _searchResults = [];
  bool _isSearching = false;
  Timer? _debounce;

  // Branch mode & selection
  bool _isNewBranch = false;
  List<Branch> _clientBranches = [];
  Branch? _selectedBranch;
  bool _loadingBranches = false;

  // Branch form fields
  final TextEditingController _nameCtrl = TextEditingController();
  final TextEditingController _codeCtrl = TextEditingController();
  final TextEditingController _addressCtrl = TextEditingController();
  final TextEditingController _sectorCtrl = TextEditingController();
  final TextEditingController _phoneCtrl = TextEditingController();
  final TextEditingController _emailCtrl = TextEditingController();

  // Map & Location
  GoogleMapController? _mapController;
  LatLng? _selectedLatLng;
  Position? _currentGpsPosition;
  bool _loadingLocation = false;
  bool _isSubmitting = false;

  static const LatLng _defaultLocation = LatLng(-2.189412, -79.889066); // Guayaquil default

  @override
  void initState() {
    super.initState();
    if (widget.initialClient != null) {
      _selectedClient = widget.initialClient;
      _loadBranchesForClient(widget.initialClient!);
    } else {
      _getCurrentLocation(animateMap: false);
    }
  }

  @override
  void dispose() {
    _clientSearchCtrl.dispose();
    _nameCtrl.dispose();
    _codeCtrl.dispose();
    _addressCtrl.dispose();
    _sectorCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _getCurrentLocation({bool animateMap = true}) async {
    setState(() => _loadingLocation = true);
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Los servicios de ubicación GPS están desactivados. Por favor actívalos.'),
              backgroundColor: Colors.orange,
            ),
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
              const SnackBar(content: Text('Permiso de GPS denegado.'), backgroundColor: Colors.red),
            );
          }
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Los permisos de ubicación están denegados permanentemente en los ajustes.'),
              backgroundColor: Colors.red,
            ),
          );
        }
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        timeLimit: const Duration(seconds: 10),
        desiredAccuracy: LocationAccuracy.high,
      );

      setState(() {
        _currentGpsPosition = pos;
        _selectedLatLng = LatLng(pos.latitude, pos.longitude);
      });

      if (animateMap && _mapController != null) {
        _mapController!.animateCamera(
          CameraUpdate.newLatLngZoom(
            LatLng(pos.latitude, pos.longitude),
            17,
          ),
        );
      }
    } catch (e) {
      debugPrint('Error obteniendo GPS: $e');
    } finally {
      if (mounted) setState(() => _loadingLocation = false);
    }
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      if (query.trim().length >= 2) {
        _searchClients(query);
      } else {
        setState(() => _searchResults = []);
      }
    });
  }

  Future<void> _searchClients(String query) async {
    setState(() => _isSearching = true);
    try {
      final syncService = context.read<SyncService>();
      if (syncService.isOnline) {
        final api = context.read<ApiService>();
        final data = await api.get('/clients/search?q=$query');
        if (data is List) {
          setState(() {
            _searchResults = data.map((json) => Client.fromJson(json)).toList();
          });
          return;
        }
      }
      // Fallback local
      final localResults = await OfflineDatabaseService.instance.searchClientsLocal(query);
      setState(() {
        _searchResults = localResults;
      });
    } catch (e) {
      debugPrint('Error en búsqueda de clientes: $e');
      final localResults = await OfflineDatabaseService.instance.searchClientsLocal(query);
      setState(() {
        _searchResults = localResults;
      });
    } finally {
      if (mounted) setState(() => _isSearching = false);
    }
  }

  void _selectClient(Client client) {
    setState(() {
      _selectedClient = client;
      _searchResults = [];
      _clientSearchCtrl.clear();
      _selectedBranch = null;
      _isNewBranch = false;
    });
    _loadBranchesForClient(client);
  }

  Future<void> _loadBranchesForClient(Client client) async {
    setState(() => _loadingBranches = true);
    try {
      final syncService = context.read<SyncService>();
      List<Branch> loadedBranches = [];

      if (syncService.isOnline) {
        final api = context.read<ApiService>();
        final data = await api.getClientBranches(client.id);
        loadedBranches = data.map((j) => Branch.fromJson(j)).toList();
        await OfflineDatabaseService.instance.saveBranches(client.id, loadedBranches);
      } else {
        loadedBranches = await OfflineDatabaseService.instance.getBranchesLocal(client.id);
      }

      setState(() {
        _clientBranches = loadedBranches;
      });

      // Si se pasó una sucursal inicial, seleccionarla
      if (widget.initialBranch != null) {
        final match = loadedBranches.where((b) => b.id == widget.initialBranch!.id).firstOrNull;
        if (match != null) {
          _onSelectBranch(match);
          return;
        }
      }

      // Si no hay sucursales, activar modo nueva sucursal automáticamente
      if (loadedBranches.isEmpty) {
        _switchToNewBranchMode();
      } else {
        // Seleccionar la primera por defecto y prellenar sus datos
        _onSelectBranch(loadedBranches.first);
      }
    } catch (e) {
      debugPrint('Error cargando sucursales: $e');
      final localBranches = await OfflineDatabaseService.instance.getBranchesLocal(client.id);
      setState(() {
        _clientBranches = localBranches;
      });
      if (localBranches.isNotEmpty) {
        _onSelectBranch(localBranches.first);
      } else {
        _switchToNewBranchMode();
      }
    } finally {
      if (mounted) setState(() => _loadingBranches = false);
    }
  }

  void _onSelectBranch(Branch branch) {
    setState(() {
      _selectedBranch = branch;
      _isNewBranch = false;

      // REQUERIMIENTO DEL USUARIO: Cargar y prellenar datos existentes de la sucursal
      _nameCtrl.text = branch.name;
      _codeCtrl.text = branch.code ?? '';
      _addressCtrl.text = branch.address ?? (_selectedClient?.address ?? '');
      _sectorCtrl.text = branch.sector ?? '';
      _phoneCtrl.text = branch.phone ?? (_selectedClient?.phone ?? '');
      _emailCtrl.text = branch.email ?? (_selectedClient?.email ?? '');

      if (branch.isGeocoded) {
        _selectedLatLng = LatLng(branch.latitude!, branch.longitude!);
        if (_mapController != null) {
          _mapController!.animateCamera(
            CameraUpdate.newLatLngZoom(_selectedLatLng!, 17),
          );
        }
      } else if (_currentGpsPosition != null) {
        _selectedLatLng = LatLng(_currentGpsPosition!.latitude, _currentGpsPosition!.longitude);
      }
    });
  }

  void _switchToNewBranchMode() {
    setState(() {
      _selectedBranch = null;
      _isNewBranch = true;

      // Valores iniciales sugeridos para nueva sucursal
      _nameCtrl.text = _selectedClient != null
          ? (_clientBranches.isEmpty ? '${_selectedClient!.name} - Matriz' : 'Sucursal ${_clientBranches.length + 1}')
          : '';
      _codeCtrl.clear();
      _addressCtrl.text = _selectedClient?.address ?? '';
      _sectorCtrl.clear();
      _phoneCtrl.text = _selectedClient?.phone ?? '';
      _emailCtrl.text = _selectedClient?.email ?? '';

      if (_currentGpsPosition != null) {
        _selectedLatLng = LatLng(_currentGpsPosition!.latitude, _currentGpsPosition!.longitude);
        if (_mapController != null) {
          _mapController!.animateCamera(
            CameraUpdate.newLatLngZoom(_selectedLatLng!, 17),
          );
        }
      }
    });
  }

  Future<void> _submitCensus() async {
    if (_selectedClient == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Por favor selecciona un cliente.'), backgroundColor: Colors.red),
      );
      return;
    }

    if (_nameCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ingresa el nombre o rótulo de la sucursal.'), backgroundColor: Colors.red),
      );
      return;
    }

    if (_selectedLatLng == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Fija la ubicación en el mapa o presiona "Capturar GPS".'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final payload = {
      'client_id': _selectedClient!.id,
      if (!_isNewBranch && _selectedBranch != null) 'branch_id': _selectedBranch!.id,
      'name': _nameCtrl.text.trim(),
      if (_codeCtrl.text.trim().isNotEmpty) 'code': _codeCtrl.text.trim(),
      if (_addressCtrl.text.trim().isNotEmpty) 'address': _addressCtrl.text.trim(),
      if (_sectorCtrl.text.trim().isNotEmpty) 'sector': _sectorCtrl.text.trim(),
      if (_phoneCtrl.text.trim().isNotEmpty) 'phone': _phoneCtrl.text.trim(),
      if (_emailCtrl.text.trim().isNotEmpty) 'email': _emailCtrl.text.trim(),
      'latitude': _selectedLatLng!.latitude,
      'longitude': _selectedLatLng!.longitude,
    };

    final syncService = context.read<SyncService>();
    final apiService = context.read<ApiService>();

    try {
      if (syncService.isOnline) {
        final res = await apiService.saveBranchCensus(payload, clientId: _selectedClient!.id);
        if (mounted) {
          _showSuccessDialog(
            isOnline: true,
            branchName: _nameCtrl.text.trim(),
            message: res['message'] ?? 'Ubicación y sucursal guardadas exitosamente en el servidor.',
          );
        }
      } else {
        await OfflineDatabaseService.instance.insertOfflineCensus(
          payload,
          clientId: _selectedClient!.id,
          clientName: _selectedClient!.name,
          branchId: _selectedBranch?.id,
        );
        syncService.checkPendingCounts();
        if (mounted) {
          _showSuccessDialog(
            isOnline: false,
            branchName: _nameCtrl.text.trim(),
            message: 'Censo guardado en la cola local. Se sincronizará automáticamente al recuperar conexión.',
          );
        }
      }
    } catch (e) {
      debugPrint('Error guardando censo: $e');
      // Si falla la petición online, guardar en cola offline como fallback seguro
      await OfflineDatabaseService.instance.insertOfflineCensus(
        payload,
        clientId: _selectedClient!.id,
        clientName: _selectedClient!.name,
        branchId: _selectedBranch?.id,
      );
      syncService.checkPendingCounts();
      if (mounted) {
        _showSuccessDialog(
          isOnline: false,
          branchName: _nameCtrl.text.trim(),
          message: 'Guardado localmente por problemas de red. Se enviará en cuanto haya conexión estable.',
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showSuccessDialog({required bool isOnline, required String branchName, required String message}) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(
              isOnline ? Icons.check_circle_rounded : Icons.cloud_queue_rounded,
              color: isOnline ? Colors.green : Colors.orange,
              size: 28,
            ),
            const SizedBox(width: 10),
            const Expanded(
              child: Text(
                'Censo Registrado',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
              ),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              branchName,
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              style: TextStyle(color: Colors.grey[700], fontSize: 14, height: 1.3),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF1FBFE),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  const Icon(Icons.pin_drop, color: Color(0xFF03A9F4), size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${_selectedLatLng?.latitude.toStringAsFixed(6)}, ${_selectedLatLng?.longitude.toStringAsFixed(6)}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0288D1)),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              // Recargar sucursales del cliente actual para censar otra o ver el cambio
              if (_selectedClient != null) {
                _loadBranchesForClient(_selectedClient!);
              }
            },
            child: const Text('Censar otra sucursal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF03A9F4),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () {
              Navigator.pop(ctx);
              Navigator.pop(context, true);
            },
            child: const Text('Finalizar'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Censo de Sucursales',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        foregroundColor: Colors.black87,
        elevation: 0.5,
        actions: [
          IconButton(
            icon: const Icon(Icons.my_location, color: Color(0xFF03A9F4)),
            tooltip: 'Mi ubicación GPS',
            onPressed: _loadingLocation ? null : () => _getCurrentLocation(animateMap: true),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeaderBanner(),
            const SizedBox(height: 16),
            _buildClientSection(),
            if (_selectedClient != null) ...[
              const SizedBox(height: 16),
              _buildBranchSelector(),
              const SizedBox(height: 16),
              _buildMapSection(),
              const SizedBox(height: 16),
              _buildBranchForm(),
              const SizedBox(height: 24),
              _buildSubmitButton(),
              const SizedBox(height: 40),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildHeaderBanner() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF03A9F4), Color(0xFF0288D1)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF03A9F4).withOpacity(0.25),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.add_location_alt_rounded, color: Colors.white, size: 28),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Mapeo Geográfico',
                  style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                ),
                SizedBox(height: 4),
                Text(
                  'Captura la ubicación exacta y datos de sucursales para optimización de rutas y entregas.',
                  style: TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildClientSection() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey[200]!),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: const Color(0xFFE1F5FE),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text('1', style: TextStyle(color: Color(0xFF0288D1), fontWeight: FontWeight.bold)),
              ),
              const SizedBox(width: 10),
              const Text(
                'Seleccionar Cliente',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_selectedClient == null) ...[
            TextField(
              controller: _clientSearchCtrl,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Buscar por nombre, RUC o local...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _isSearching
                    ? const Padding(
                        padding: EdgeInsets.all(12.0),
                        child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                      )
                    : null,
                filled: true,
                fillColor: const Color(0xFFF8FAFC),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: Colors.grey[300]!),
                ),
              ),
            ),
            if (_searchResults.isNotEmpty)
              Container(
                margin: const EdgeInsets.only(top: 8),
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey[200]!),
                  borderRadius: BorderRadius.circular(12),
                ),
                constraints: const BoxConstraints(maxHeight: 220),
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: _searchResults.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (ctx, i) {
                    final c = _searchResults[i];
                    return ListTile(
                      dense: true,
                      leading: const CircleAvatar(
                        radius: 16,
                        backgroundColor: Color(0xFFE1F5FE),
                        child: Icon(Icons.business, size: 16, color: Color(0xFF03A9F4)),
                      ),
                      title: Text(c.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      subtitle: Text(
                        'RUC/CI: ${c.identification ?? 'S/N'} • ${c.nombreLocal ?? c.address ?? ''}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 11),
                      ),
                      onTap: () => _selectClient(c),
                    );
                  },
                ),
              ),
          ] else ...[
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFF1FBFE),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFB3E5FC)),
              ),
              child: Row(
                children: [
                  const CircleAvatar(
                    backgroundColor: Color(0xFF03A9F4),
                    child: Icon(Icons.person, color: Colors.white),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _selectedClient!.name,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                        ),
                        if (_selectedClient!.identification != null)
                          Text(
                            'RUC/CI: ${_selectedClient!.identification}',
                            style: TextStyle(color: Colors.grey[700], fontSize: 12),
                          ),
                        if (_selectedClient!.address != null)
                          Text(
                            _selectedClient!.address!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(color: Colors.grey[600], fontSize: 11),
                          ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.change_circle_outlined, color: Color(0xFF0288D1)),
                    tooltip: 'Cambiar cliente',
                    onPressed: () {
                      setState(() {
                        _selectedClient = null;
                        _selectedBranch = null;
                        _clientBranches = [];
                      });
                    },
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildBranchSelector() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey[200]!),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE1F5FE),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text('2', style: TextStyle(color: Color(0xFF0288D1), fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(width: 10),
                  const Text(
                    'Sucursal a Censar',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              OutlinedButton.icon(
                onPressed: _switchToNewBranchMode,
                icon: const Icon(Icons.add, size: 16),
                label: const Text('Nueva', style: TextStyle(fontSize: 12)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF03A9F4),
                  side: const BorderSide(color: Color(0xFF03A9F4)),
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_loadingBranches)
            const Center(child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator()))
          else if (_clientBranches.isEmpty)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.amber[50],
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.amber[200]!),
              ),
              child: const Row(
                children: [
                  Icon(Icons.info_outline, color: Colors.amber, size: 20),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Este cliente aún no tiene sucursales registradas. Registraremos la primera ahora.',
                      style: TextStyle(fontSize: 12, color: Colors.black87),
                    ),
                  ),
                ],
              ),
            )
          else ...[
            Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey[300]!),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<Branch>(
                  isExpanded: true,
                  value: _isNewBranch ? null : _selectedBranch,
                  hint: const Text('Seleccionar sucursal existente'),
                  items: _clientBranches.map((b) {
                    final bool isGeo = b.isGeocoded;
                    return DropdownMenuItem<Branch>(
                      value: b,
                      child: Row(
                        children: [
                          Icon(
                            isGeo ? Icons.location_on : Icons.location_off_outlined,
                            size: 18,
                            color: isGeo ? Colors.green : Colors.orange,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              b.name,
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: isGeo ? Colors.green[50] : Colors.orange[50],
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              isGeo ? '📍 Mapeada' : '⚠️ Sin GPS',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: isGeo ? Colors.green[800] : Colors.orange[800],
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  }).toList(),
                  onChanged: (b) {
                    if (b != null) _onSelectBranch(b);
                  },
                ),
              ),
            ),
            if (_isNewBranch)
              Container(
                margin: const EdgeInsets.only(top: 8),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFE8F5E9),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.green[300]!),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.add_circle, color: Colors.green, size: 16),
                    SizedBox(width: 8),
                    Text(
                      'Modo: Creando nueva sucursal',
                      style: TextStyle(color: Colors.green, fontWeight: FontWeight.bold, fontSize: 12),
                    ),
                  ],
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _buildMapSection() {
    final LatLng center = _selectedLatLng ??
        (_currentGpsPosition != null
            ? LatLng(_currentGpsPosition!.latitude, _currentGpsPosition!.longitude)
            : _defaultLocation);

    final Set<Marker> markers = {
      if (_selectedLatLng != null)
        Marker(
          markerId: const MarkerId('census_pin'),
          position: _selectedLatLng!,
          draggable: true,
          infoWindow: InfoWindow(
            title: _nameCtrl.text.isNotEmpty ? _nameCtrl.text : 'Ubicación de Sucursal',
            snippet: 'Arrastra el pin para ajustar la posición exacta',
          ),
          onDragEnd: (newPos) {
            setState(() {
              _selectedLatLng = newPos;
            });
          },
        ),
    };

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey[200]!),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE1F5FE),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text('3', style: TextStyle(color: Color(0xFF0288D1), fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(width: 10),
                  const Text(
                    'Ubicación Geográfica',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: _loadingLocation ? null : () => _getCurrentLocation(animateMap: true),
                icon: _loadingLocation
                    ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.gps_fixed, size: 14),
                label: const Text('GPS Actual', style: TextStyle(fontSize: 12)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF03A9F4),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'Toca el mapa o arrastra el marcador 📍 sobre la puerta o local del cliente.',
            style: TextStyle(fontSize: 12, color: Colors.grey),
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: SizedBox(
              height: 240,
              child: Stack(
                children: [
                  GoogleMap(
                    initialCameraPosition: CameraPosition(target: center, zoom: 16),
                    onMapCreated: (ctrl) {
                      _mapController = ctrl;
                      if (_selectedLatLng != null) {
                        ctrl.animateCamera(CameraUpdate.newLatLngZoom(_selectedLatLng!, 16));
                      }
                    },
                    markers: markers,
                    onTap: (latLng) {
                      setState(() {
                        _selectedLatLng = latLng;
                      });
                    },
                    myLocationEnabled: true,
                    myLocationButtonEnabled: false,
                    zoomControlsEnabled: false,
                  ),
                  Positioned(
                    bottom: 8,
                    left: 8,
                    right: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.black87.withOpacity(0.85),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.pin_drop, color: Colors.cyanAccent, size: 16),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              _selectedLatLng != null
                                  ? 'Lat: ${_selectedLatLng!.latitude.toStringAsFixed(6)} | Lng: ${_selectedLatLng!.longitude.toStringAsFixed(6)}'
                                  : 'Toca en el mapa para marcar la ubicación',
                              style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w500),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBranchForm() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey[200]!),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: const Color(0xFFE1F5FE),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text('4', style: TextStyle(color: Color(0xFF0288D1), fontWeight: FontWeight.bold)),
              ),
              const SizedBox(width: 10),
              const Text(
                'Datos de la Sucursal',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _nameCtrl,
            readOnly: !_isNewBranch && _selectedBranch != null,
            decoration: InputDecoration(
              labelText: 'Nombre o Rótulo de la Sucursal *',
              hintText: 'Ej. Matriz Centro, Sucursal Norte, Local 2',
              prefixIcon: Icon(!_isNewBranch && _selectedBranch != null ? Icons.lock_outline : Icons.storefront_outlined),
              suffixIcon: !_isNewBranch && _selectedBranch != null
                  ? const Tooltip(
                      message: 'Dato oficial no modificable',
                      child: Icon(Icons.check_circle, color: Colors.green, size: 18),
                    )
                  : null,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: _sectorCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Sector / Parroquia',
                    hintText: 'Ej. Urdesa, Alborada, Centro',
                    prefixIcon: Icon(Icons.map_outlined),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 1,
                child: TextFormField(
                  controller: _codeCtrl,
                  readOnly: !_isNewBranch && _selectedBranch != null && (_selectedBranch!.code?.isNotEmpty ?? false),
                  decoration: InputDecoration(
                    labelText: 'Código / Ref',
                    hintText: 'SUC-01',
                    prefixIcon: Icon(!_isNewBranch && _selectedBranch != null && (_selectedBranch!.code?.isNotEmpty ?? false)
                        ? Icons.lock_outline
                        : Icons.tag),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _addressCtrl,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Dirección Exacta / Referencia de Ubicación',
              hintText: 'Calles principales, intersección, frente a local comercial...',
              prefixIcon: Icon(Icons.signpost_outlined),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: _phoneCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Teléfono Contacto',
                    hintText: '0987654321',
                    prefixIcon: Icon(Icons.phone_outlined),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextFormField(
                  controller: _emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Email Sucursal',
                    hintText: 'local@correo.com',
                    prefixIcon: Icon(Icons.email_outlined),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton(
        onPressed: _isSubmitting ? null : _submitCensus,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF03A9F4),
          foregroundColor: Colors.white,
          elevation: 2,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        child: _isSubmitting
            ? const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                  SizedBox(width: 12),
                  Text('Guardando Censo...', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ],
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.save_rounded),
                  const SizedBox(width: 10),
                  Text(
                    _isNewBranch ? 'Registrar Nueva Sucursal' : 'Actualizar Censo de Sucursal',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ],
              ),
      ),
    );
  }
}
