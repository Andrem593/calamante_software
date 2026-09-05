import 'dart:convert';
import 'package:path/path.dart';
import 'package:sqflite/sqflite.dart';
import '../models/models.dart';

class OfflineDatabaseService {
  static final OfflineDatabaseService instance = OfflineDatabaseService._init();
  static Database? _database;

  OfflineDatabaseService._init();

  Future<Database> get database async {
    if (_database != null) return _database!;
    _database = await _initDB('dados_offline.db');
    return _database!;
  }

  Future<Database> _initDB(String filePath) async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, filePath);

    return await openDatabase(
      path,
      version: 1,
      onCreate: _createDB,
    );
  }

  Future<void> _createDB(Database db, int version) async {
    await db.execute('''
      CREATE TABLE cached_clients (
        id INTEGER PRIMARY KEY,
        name TEXT,
        email TEXT,
        address TEXT,
        identification TEXT,
        identification_type TEXT,
        nombre_local TEXT,
        company_name TEXT,
        phone TEXT,
        credit_limit REAL,
        credit_days INTEGER
      )
    ''');

    await db.execute('''
      CREATE TABLE cached_branches (
        id INTEGER PRIMARY KEY,
        client_id INTEGER,
        name TEXT,
        code TEXT,
        address TEXT,
        email TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE cached_products (
        id INTEGER PRIMARY KEY,
        name TEXT,
        price REAL,
        stock INTEGER,
        tax_percentage REAL,
        category_id INTEGER,
        discount_percentage REAL
      )
    ''');

    await db.execute('''
      CREATE TABLE cached_categories (
        id INTEGER PRIMARY KEY,
        name TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE cached_equipments (
        id INTEGER PRIMARY KEY,
        model TEXT,
        capacity TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE cached_client_equipments (
        client_id INTEGER,
        branch_id INTEGER,
        equipment_id INTEGER
      )
    ''');

    await db.execute('''
      CREATE TABLE offline_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        local_uuid TEXT,
        client_id INTEGER,
        client_name TEXT,
        total REAL,
        payload_json TEXT,
        created_at TEXT,
        is_synced INTEGER DEFAULT 0,
        sync_error TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE offline_visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER,
        client_name TEXT,
        payload_json TEXT,
        created_at TEXT,
        is_synced INTEGER DEFAULT 0,
        sync_error TEXT
      )
    ''');
  }

  // ── CLIENTES ─────────────────────────────────────────────────────────────────
  Future<void> saveClients(List<Client> clients, {bool clearFirst = false}) async {
    final db = await instance.database;
    if (clearFirst) {
      await db.delete('cached_clients');
    }
    final batch = db.batch();
    for (var c in clients) {
      batch.insert(
        'cached_clients',
        {
          'id': c.id,
          'name': c.name,
          'email': c.email,
          'address': c.address,
          'identification': c.identification,
          'identification_type': c.identificationType,
          'nombre_local': c.nombreLocal,
          'company_name': c.companyName,
          'phone': c.phone,
          'credit_limit': c.creditLimit,
          'credit_days': c.creditDays,
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
  }

  Future<List<Client>> searchClientsLocal(String query) async {
    final db = await instance.database;
    final q = '%${query.toLowerCase()}%';
    final maps = await db.query(
      'cached_clients',
      where: 'LOWER(name) LIKE ? OR LOWER(nombre_local) LIKE ? OR LOWER(identification) LIKE ? OR LOWER(company_name) LIKE ?',
      whereArgs: [q, q, q, q],
      limit: 20,
    );
    return maps.map((json) => Client.fromJson(json)).toList();
  }

  Future<List<Client>> getAllClientsLocal() async {
    final db = await instance.database;
    final maps = await db.query('cached_clients', orderBy: 'name ASC');
    return maps.map((json) => Client.fromJson(json)).toList();
  }

  // ── SUCURSALES ──────────────────────────────────────────────────────────────
  Future<void> saveBranches(int clientId, List<Branch> branches) async {
    final db = await instance.database;
    final batch = db.batch();
    batch.delete('cached_branches', where: 'client_id = ?', whereArgs: [clientId]);
    for (var b in branches) {
      batch.insert(
        'cached_branches',
        {
          'id': b.id,
          'client_id': clientId,
          'name': b.name,
          'code': b.code,
          'address': b.address,
          'email': b.email,
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
  }

  Future<List<Branch>> getBranchesLocal(int clientId) async {
    final db = await instance.database;
    final maps = await db.query('cached_branches', where: 'client_id = ?', whereArgs: [clientId]);
    return maps.map((json) => Branch.fromJson(json)).toList();
  }

  // ── PRODUCTOS Y CATEGORÍAS ─────────────────────────────────────────────────
  Future<void> saveProducts(List<Product> products) async {
    final db = await instance.database;
    final batch = db.batch();
    for (var p in products) {
      batch.insert(
        'cached_products',
        {
          'id': p.id,
          'name': p.name,
          'price': p.price,
          'stock': p.stock,
          'tax_percentage': p.taxPercentage,
          'category_id': p.categoryId,
          'discount_percentage': p.discountPercentage,
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
  }

  Future<List<Product>> getProductsLocal({int? categoryId}) async {
    final db = await instance.database;
    final maps = categoryId != null
        ? await db.query('cached_products', where: 'category_id = ?', whereArgs: [categoryId])
        : await db.query('cached_products');
    return maps.map((json) => Product.fromJson(json)).toList();
  }

  Future<void> saveCategories(List<Category> categories) async {
    final db = await instance.database;
    final batch = db.batch();
    for (var cat in categories) {
      batch.insert(
        'cached_categories',
        {
          'id': cat.id,
          'name': cat.name,
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
  }

  Future<List<Category>> getCategoriesLocal() async {
    final db = await instance.database;
    final maps = await db.query('cached_categories');
    return maps.map((json) => Category.fromJson(json)).toList();
  }

  // ── EQUIPOS ──────────────────────────────────────────────────────────────────
  Future<void> saveEquipments(List<dynamic> equipments) async {
    final db = await instance.database;
    final batch = db.batch();
    for (var eq in equipments) {
      batch.insert(
        'cached_equipments',
        {
          'id': eq['id'],
          'model': eq['model'],
          'capacity': eq['capacity'],
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    }
    await batch.commit(noResult: true);
  }

  Future<List<dynamic>> getEquipmentsLocal() async {
    final db = await instance.database;
    final maps = await db.query('cached_equipments');
    return maps;
  }

  // ── PEDIDOS EN COLA OFFLINE ──────────────────────────────────────────────
  Future<int> insertOfflineOrder(
    Map<String, dynamic> payload, {
    required int clientId,
    required String clientName,
    required double total,
  }) async {
    final db = await instance.database;
    final String nowStr = DateTime.now().toIso8601String();
    return await db.insert('offline_orders', {
      'local_uuid': 'OFFLINE_${DateTime.now().millisecondsSinceEpoch}',
      'client_id': clientId,
      'client_name': clientName,
      'total': total,
      'payload_json': jsonEncode(payload),
      'created_at': nowStr,
      'is_synced': 0,
      'sync_error': null,
    });
  }

  Future<List<Map<String, dynamic>>> getUnsyncedOrders() async {
    final db = await instance.database;
    return await db.query(
      'offline_orders',
      where: 'is_synced = 0',
      orderBy: 'id ASC',
    );
  }

  Future<int> getUnsyncedOrdersCount() async {
    final db = await instance.database;
    final count = Sqflite.firstIntValue(
      await db.rawQuery('SELECT COUNT(*) FROM offline_orders WHERE is_synced = 0'),
    );
    return count ?? 0;
  }

  Future<void> markOrderSynced(int localId) async {
    final db = await instance.database;
    await db.update(
      'offline_orders',
      {'is_synced': 1, 'sync_error': null},
      where: 'id = ?',
      whereArgs: [localId],
    );
  }

  Future<void> updateOrderSyncError(int localId, String error) async {
    final db = await instance.database;
    await db.update(
      'offline_orders',
      {'sync_error': error},
      where: 'id = ?',
      whereArgs: [localId],
    );
  }

  // ── VISITAS EN COLA OFFLINE ──────────────────────────────────────────────
  Future<int> insertOfflineVisit(
    Map<String, dynamic> payload, {
    required int clientId,
    required String clientName,
  }) async {
    final db = await instance.database;
    final String nowStr = DateTime.now().toIso8601String();
    return await db.insert('offline_visits', {
      'client_id': clientId,
      'client_name': clientName,
      'payload_json': jsonEncode(payload),
      'created_at': nowStr,
      'is_synced': 0,
      'sync_error': null,
    });
  }

  Future<List<Map<String, dynamic>>> getUnsyncedVisits() async {
    final db = await instance.database;
    return await db.query(
      'offline_visits',
      where: 'is_synced = 0',
      orderBy: 'id ASC',
    );
  }

  Future<void> markVisitSynced(int localId) async {
    final db = await instance.database;
    await db.update(
      'offline_visits',
      {'is_synced': 1, 'sync_error': null},
      where: 'id = ?',
      whereArgs: [localId],
    );
  }
}
