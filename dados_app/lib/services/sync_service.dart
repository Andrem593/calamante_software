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
      final orders = await _db.getUnsyncedOrdersCount();
      final censuses = await _db.getUnsyncedCensusesCount();
      _pendingOrdersCount = orders + censuses;
      notifyListeners();
    } catch (e) {
      debugPrint('Error contando registros pendientes: $e');
    }
  }

  Future<bool> syncSingleOrder(int localId, ApiService apiService) async {
    final orderRow = await _db.getOfflineOrderById(localId);
    if (orderRow == null) {
      throw Exception('Pedido local #$localId no encontrado.');
    }

    final String payloadJson = orderRow['payload_json'];
    final Map<String, dynamic> payload = jsonDecode(payloadJson);

    // CRÍTICO: Conservar la fecha de creación original registrada en offline_orders
    if (orderRow['created_at'] != null) {
      payload['created_at'] ??= orderRow['created_at'];
    }

    try {
      await apiService.post('/orders', payload);
      await _db.markOrderSynced(localId);
      await checkPendingCounts();
      return true;
    } catch (e) {
      debugPrint('Error sincronizando pedido individual local #$localId: $e');
      final errorStr = e.toString().toLowerCase();
      if (errorStr.contains('duplicado') || errorStr.contains('already exists')) {
        await _db.markOrderSynced(localId);
        await checkPendingCounts();
        return true;
      }
      await _db.updateOrderSyncError(localId, e.toString());
      await checkPendingCounts();
      rethrow;
    }
  }

  Future<int> reconcileWithRemoteOrders(List<dynamic> remoteOrders) async {
    if (remoteOrders.isEmpty) return 0;
    final unsynced = await _db.getUnsyncedOrders();
    if (unsynced.isEmpty) return 0;

    int reconciledCount = 0;
    for (var offlineOrder in unsynced) {
      final int localId = offlineOrder['id'];
      final int clientId = offlineOrder['client_id'];
      final double total = (offlineOrder['total'] as num?)?.toDouble() ?? 0.0;
      final String? createdAtStr = offlineOrder['created_at'];

      final alreadyOnServer = remoteOrders.any((remote) {
        final remoteClientId = remote['client_id'] ?? remote['client']?['id'];
        if (remoteClientId != clientId) return false;

        final double remoteTotal = double.tryParse(remote['total']?.toString() ?? '0.0') ?? 0.0;
        if ((remoteTotal - total).abs() > 0.05) return false;

        if (createdAtStr != null && remote['created_at'] != null) {
          try {
            final dtOff = DateTime.parse(createdAtStr);
            final dtRem = DateTime.parse(remote['created_at'].toString());
            if (dtOff.year == dtRem.year && dtOff.month == dtRem.month && dtOff.day == dtRem.day) {
              return true;
            }
          } catch (_) {}
        }
        return false;
      });

      if (alreadyOnServer) {
        debugPrint('SyncService: Reconciliando pedido local #$localId (ya existe en servidor). Marcando como sincronizado.');
        await _db.markOrderSynced(localId);
        reconciledCount++;
      }
    }

    if (reconciledCount > 0) {
      await checkPendingCounts();
    }
    return reconciledCount;
  }

  Future<void> syncPendingData(ApiService apiService) async {
    if (_isSyncing) return;
    _isSyncing = true;
    notifyListeners();

    try {
      final now = DateTime.now();

      // 1. Sincronizar Pedidos en cola: ÚNICAMENTE los del MISMO DÍA (hoy)
      final pendingOrders = await _db.getUnsyncedOrders();
      int successOrders = 0;

      for (var orderRow in pendingOrders) {
        final int localId = orderRow['id'];
        final String? createdAtStr = orderRow['created_at'];

        // Comprobar si el documento fue creado hoy
        bool isSameDay = false;
        if (createdAtStr != null) {
          try {
            final dt = DateTime.parse(createdAtStr);
            isSameDay = (dt.year == now.year && dt.month == now.month && dt.day == now.day);
          } catch (_) {}
        }

        // Si es de un día anterior, NO auto-sincronizar en segundo plano (requiere sincronización individual)
        if (!isSameDay) {
          debugPrint('SyncService: Pedido local #$localId de fecha anterior ($createdAtStr) omitido de auto-sync masivo.');
          continue;
        }

        final String payloadJson = orderRow['payload_json'];
        final Map<String, dynamic> payload = jsonDecode(payloadJson);

        if (createdAtStr != null && payload['created_at'] == null) {
          payload['created_at'] = createdAtStr;
        }

        try {
          await apiService.post('/orders', payload);
          await _db.markOrderSynced(localId);
          successOrders++;
          debugPrint('SyncService: Pedido del día local #$localId auto-sincronizado con éxito.');
        } catch (e) {
          debugPrint('Error auto-sincronizando pedido local #$localId: $e');
          final errorStr = e.toString().toLowerCase();
          if (errorStr.contains('duplicado') || errorStr.contains('already exists')) {
            await _db.markOrderSynced(localId);
            successOrders++;
          } else {
            await _db.updateOrderSyncError(localId, e.toString());
          }
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

      // 3. Sincronizar Censos en cola
      final pendingCensuses = await _db.getUnsyncedCensuses();
      int successCensuses = 0;
      for (var censusRow in pendingCensuses) {
        final int localId = censusRow['id'];
        final int clientId = censusRow['client_id'];
        final String payloadJson = censusRow['payload_json'];
        final Map<String, dynamic> payload = jsonDecode(payloadJson);

        try {
          await apiService.saveBranchCensus(payload, clientId: clientId);
          await _db.markCensusSynced(localId);
          successCensuses++;
        } catch (e) {
          debugPrint('Error sincronizando censo local #$localId: $e');
          await _db.updateCensusSyncError(localId, e.toString());
        }
      }

      // 4. Reconciliar pedidos con el servidor para verificar si ya existen y desmarcarlos de pendientes
      try {
        final remoteOrders = await apiService.get('/orders');
        if (remoteOrders is List) {
          await reconcileWithRemoteOrders(remoteOrders);
        }
      } catch (e) {
        debugPrint('Error verificando pedidos con el servidor: $e');
      }

      await checkPendingCounts();

      List<String> messages = [];
      if (successOrders > 0) messages.add('$successOrders pedido(s) del día');
      if (successCensuses > 0) messages.add('$successCensuses censo(s)');
      _lastSyncMessage = messages.isNotEmpty
          ? 'Se sincronizaron ${messages.join(' y ')} pendientes.'
          : null;
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
