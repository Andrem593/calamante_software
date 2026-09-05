import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart' hide Category;
import '../models/models.dart';
import 'api_service.dart';
import 'offline_database_service.dart';

class SyncService extends ChangeNotifier {
  bool _isOnline = true;
  bool _isSyncing = false;
  int _pendingOrdersCount = 0;
  String? _lastSyncMessage;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  bool get isOnline => _isOnline;
  bool get isSyncing => _isSyncing;
  int get pendingOrdersCount => _pendingOrdersCount;
  String? get lastSyncMessage => _lastSyncMessage;

  final OfflineDatabaseService _db = OfflineDatabaseService.instance;

  void init(ApiService apiService) {
    _checkInitialConnectivity(apiService);
    _listenToConnectivity(apiService);
    checkPendingCounts();
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }

  Future<void> _checkInitialConnectivity(ApiService apiService) async {
    try {
      final results = await Connectivity().checkConnectivity();
      _updateOnlineStatus(results);
      if (_isOnline) {
        syncPendingData(apiService);
        refreshCatalogs(apiService);
      }
    } catch (e) {
      debugPrint('Error en _checkInitialConnectivity: $e');
    }
  }

  void _listenToConnectivity(ApiService apiService) {
    _connectivitySubscription = Connectivity()
        .onConnectivityChanged
        .listen((List<ConnectivityResult> results) {
      final wasOnline = _isOnline;
      _updateOnlineStatus(results);

      if (!wasOnline && _isOnline) {
        debugPrint('SyncService: Conexión recuperada. Iniciando auto-sincronización...');
        syncPendingData(apiService);
        refreshCatalogs(apiService);
      }
    });
  }

  void _updateOnlineStatus(List<ConnectivityResult> results) {
    final bool hasConnection = results.any((r) =>
        r == ConnectivityResult.mobile ||
        r == ConnectivityResult.wifi ||
        r == ConnectivityResult.ethernet);
    if (_isOnline != hasConnection) {
      _isOnline = hasConnection;
      notifyListeners();
    }
  }

  Future<void> checkPendingCounts() async {
    try {
      _pendingOrdersCount = await _db.getUnsyncedOrdersCount();
      notifyListeners();
    } catch (e) {
      debugPrint('Error contando registros pendientes: $e');
    }
  }

  Future<void> syncPendingData(ApiService apiService) async {
    if (_isSyncing) return;
    _isSyncing = true;
    notifyListeners();

    try {
      // 1. Sincronizar Pedidos en cola
      final pendingOrders = await _db.getUnsyncedOrders();
      int successOrders = 0;

      for (var orderRow in pendingOrders) {
        final int localId = orderRow['id'];
        final String payloadJson = orderRow['payload_json'];
        final Map<String, dynamic> payload = jsonDecode(payloadJson);

        try {
          await apiService.post('/orders', payload);
          await _db.markOrderSynced(localId);
          successOrders++;
        } catch (e) {
          debugPrint('Error sincronizando pedido local #$localId: $e');
          await _db.updateOrderSyncError(localId, e.toString());
        }
      }

      // 2. Sincronizar Visitas en cola
      final pendingVisits = await _db.getUnsyncedVisits();
      for (var visitRow in pendingVisits) {
        final int localId = visitRow['id'];
        final String payloadJson = visitRow['payload_json'];
        final Map<String, dynamic> payload = jsonDecode(payloadJson);

        try {
          await apiService.post('/visits', payload);
          await _db.markVisitSynced(localId);
        } catch (e) {
          debugPrint('Error sincronizando visita local #$localId: $e');
        }
      }

      await checkPendingCounts();

      if (successOrders > 0) {
        _lastSyncMessage = 'Se sincronizaron $successOrders pedido(s) pendientes.';
      } else {
        _lastSyncMessage = null;
      }
    } catch (e) {
      debugPrint('Error durante syncPendingData: $e');
    } finally {
      _isSyncing = false;
      notifyListeners();
    }
  }

  Future<void> refreshCatalogs(ApiService apiService) async {
    if (!_isOnline) return;
    try {
      debugPrint('SyncService: Descargando catálogos para caché local...');

      // 1. Productos
      final productsData = await apiService.get('/products');
      if (productsData is List) {
        final products = productsData.map((j) => Product.fromJson(j)).toList();
        await _db.saveProducts(products);
      }

      // 2. Categorías
      final categoriesData = await apiService.get('/products/categories');
      if (categoriesData is List) {
        final categories = categoriesData.map((j) => Category.fromJson(j)).toList();
        await _db.saveCategories(categories);
      }

      // 3. Equipos
      final equipmentsData = await apiService.getEquipments();
      await _db.saveEquipments(equipmentsData);

      // 4. Clientes
      final clientsData = await apiService.get('/clients');
      if (clientsData is List) {
        final clients = clientsData.map((j) => Client.fromJson(j)).toList();
        await _db.saveClients(clients, clearFirst: true);
      }

      debugPrint('SyncService: Catálogos locales actualizados con éxito.');
    } catch (e) {
      debugPrint('Error actualizando catálogos locales: $e');
    }
  }
}
