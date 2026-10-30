class Branch {
  final int id;
  final int? clientId;
  final String name;
  final String? code;
  final String? brandName;
  final String? address;
  final String? phone;
  final String? email;
  final String? sector;
  final String? parroquia;
  final double? latitude;
  final double? longitude;
  final DateTime? geocodedAt;

  Branch({
    required this.id,
    this.clientId,
    required this.name,
    this.code,
    this.brandName,
    this.address,
    this.phone,
    this.email,
    this.sector,
    this.parroquia,
    this.latitude,
    this.longitude,
    this.geocodedAt,
  });

  bool get isGeocoded => latitude != null && longitude != null;

  factory Branch.fromJson(Map<String, dynamic> json) {
    return Branch(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      clientId: json['client_id'] != null ? int.tryParse(json['client_id'].toString()) : null,
      name: json['name']?.toString() ?? 'Sucursal',
      code: json['code']?.toString(),
      brandName: json['brand_name']?.toString(),
      address: json['address']?.toString(),
      phone: json['phone']?.toString(),
      email: json['email']?.toString(),
      sector: json['sector']?.toString(),
      parroquia: json['parroquia']?.toString(),
      latitude: json['latitude'] != null ? double.tryParse(json['latitude'].toString()) : null,
      longitude: json['longitude'] != null ? double.tryParse(json['longitude'].toString()) : null,
      geocodedAt: json['geocoded_at'] != null ? DateTime.tryParse(json['geocoded_at'].toString()) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'client_id': clientId,
      'name': name,
      'code': code,
      'brand_name': brandName,
      'address': address,
      'phone': phone,
      'email': email,
      'sector': sector,
      'parroquia': parroquia,
      'latitude': latitude,
      'longitude': longitude,
      'geocoded_at': geocodedAt?.toIso8601String(),
    };
  }
}

class Client {
  final int id;
  final String name;
  final String? email;
  final String? address;
  final String? identification;
  final String? identificationType;
  final String? nombreLocal;
  final String? companyName;
  final String? phone;
  final double? creditLimit;
  final int? creditDays;
  final bool isBlocked;
  final DateTime? unblockedUntil;
  final String? blockReason;
  final List<Branch> branches;

  Client({
    required this.id,
    required this.name,
    this.email,
    this.address,
    this.identification,
    this.identificationType,
    this.nombreLocal,
    this.companyName,
    this.phone,
    this.creditLimit,
    this.creditDays,
    this.isBlocked = false,
    this.unblockedUntil,
    this.blockReason,
    this.branches = const [],
  });

  bool get isTemporarilyUnblocked {
    if (unblockedUntil == null) return false;
    return DateTime.now().isBefore(unblockedUntil!);
  }

  bool get isBlockedForSales {
    if (isTemporarilyUnblocked) return false;
    return isBlocked;
  }

  factory Client.fromJson(Map<String, dynamic> json) {
    final String parsedName = (json['name']?.toString().isNotEmpty == true)
        ? json['name'].toString()
        : ((json['company_name']?.toString().isNotEmpty == true)
            ? json['company_name'].toString()
            : ((json['nombre_local']?.toString().isNotEmpty == true)
                ? json['nombre_local'].toString()
                : 'Cliente sin nombre'));

    return Client(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: parsedName,
      email: json['email']?.toString(),
      address: json['address']?.toString(),
      identification: json['identification']?.toString(),
      identificationType: json['identification_type']?.toString(),
      nombreLocal: json['nombre_local']?.toString() ?? json['nombreLocal']?.toString(),
      companyName: json['company_name']?.toString() ?? json['companyName']?.toString(),
      phone: json['phone']?.toString(),
      creditLimit: json['credit_limit'] != null ? double.tryParse(json['credit_limit'].toString()) : null,
      creditDays: json['credit_days'] != null ? int.tryParse(json['credit_days'].toString()) : null,
      isBlocked: json['is_blocked'] == true || json['is_blocked'] == 1 || json['is_blocked'] == '1',
      unblockedUntil: json['unblocked_until'] != null ? DateTime.tryParse(json['unblocked_until'].toString()) : null,
      blockReason: json['block_reason']?.toString(),
      branches: json['branches'] != null && json['branches'] is List
          ? (json['branches'] as List).map((b) => Branch.fromJson(b as Map<String, dynamic>)).toList()
          : [],
    );
  }
}

class PendingDocument {
  final String? id;
  final String? documento;
  final String? fechaEmision;
  final String? fechaVencimiento;
  final double total;
  final double saldo;
  final bool isOverdue;
  final int daysOverdue;

  PendingDocument({
    this.id,
    this.documento,
    this.fechaEmision,
    this.fechaVencimiento,
    required this.total,
    required this.saldo,
    this.isOverdue = false,
    this.daysOverdue = 0,
  });

  factory PendingDocument.fromJson(Map<String, dynamic> json) {
    return PendingDocument(
      id: json['id']?.toString(),
      documento: json['documento']?.toString(),
      fechaEmision: json['fecha_emision']?.toString(),
      fechaVencimiento: json['fecha_vencimiento']?.toString(),
      total: double.tryParse(json['total']?.toString() ?? '0') ?? 0.0,
      saldo: double.tryParse(json['saldo']?.toString() ?? '0') ?? 0.0,
      isOverdue: json['is_overdue'] == true || json['is_overdue'] == 1 || json['is_overdue'] == '1',
      daysOverdue: json['days_overdue'] != null ? (int.tryParse(json['days_overdue'].toString()) ?? 0) : 0,
    );
  }
}

class ClientCreditStatus {
  final int clientId;
  final String? identification;
  final double? creditLimit;
  final int? creditDays;
  final bool hasCreditConfigured;
  final bool isBlocked;
  final DateTime? unblockedUntil;
  final String? unblockTimeRemaining;
  final bool isBlockedForSales;
  final double totalPendiente;
  final double totalVencido;
  final int countPendientes;
  final int countVencidos;
  final double? availableCredit;
  final List<PendingDocument> documents;

  ClientCreditStatus({
    required this.clientId,
    this.identification,
    this.creditLimit,
    this.creditDays,
    required this.hasCreditConfigured,
    this.isBlocked = false,
    this.unblockedUntil,
    this.unblockTimeRemaining,
    this.isBlockedForSales = false,
    required this.totalPendiente,
    required this.totalVencido,
    required this.countPendientes,
    required this.countVencidos,
    this.availableCredit,
    required this.documents,
  });

  factory ClientCreditStatus.fromJson(Map<String, dynamic> json) {
    final bool rawBlockedForSales = json['is_blocked_for_sales'] == true || json['is_blocked_for_sales'] == 1 || json['is_blocked_for_sales'] == '1';
    final int cntVencidos = json['count_vencidos'] != null ? (int.tryParse(json['count_vencidos'].toString()) ?? 0) : 0;
    final double totVencido = double.tryParse(json['total_vencido']?.toString() ?? '0') ?? 0.0;
    final DateTime? unblockUntil = json['unblocked_until'] != null ? DateTime.tryParse(json['unblocked_until'].toString()) : null;
    final bool isUnblockActive = unblockUntil != null && DateTime.now().isBefore(unblockUntil);

    final bool isBlockedForSalesCalc = !isUnblockActive && (rawBlockedForSales || cntVencidos > 0 || totVencido > 0 || json['is_blocked'] == true || json['is_blocked'] == 1 || json['is_blocked'] == '1');

    return ClientCreditStatus(
      clientId: int.tryParse(json['client_id']?.toString() ?? '0') ?? 0,
      identification: json['identification']?.toString(),
      creditLimit: json['credit_limit'] != null ? double.tryParse(json['credit_limit'].toString()) : null,
      creditDays: json['credit_days'] != null ? int.tryParse(json['credit_days'].toString()) : null,
      hasCreditConfigured: json['has_credit_configured'] == true || json['has_credit_configured'] == 1 || json['has_credit_configured'] == '1',
      isBlocked: json['is_blocked'] == true || json['is_blocked'] == 1 || json['is_blocked'] == '1',
      unblockedUntil: unblockUntil,
      unblockTimeRemaining: json['unblock_time_remaining']?.toString(),
      isBlockedForSales: isBlockedForSalesCalc,
      totalPendiente: double.tryParse(json['total_pendiente']?.toString() ?? '0') ?? 0.0,
      totalVencido: totVencido,
      countPendientes: json['count_pendientes'] != null ? (int.tryParse(json['count_pendientes'].toString()) ?? 0) : 0,
      countVencidos: cntVencidos,
      availableCredit: json['available_credit'] != null ? double.tryParse(json['available_credit'].toString()) : null,
      documents: json['documents'] != null && json['documents'] is List
          ? (json['documents'] as List<dynamic>).map((d) => PendingDocument.fromJson(d as Map<String, dynamic>)).toList()
          : [],
    );
  }

  bool get hasOverdueInvoices => countVencidos > 0 || totalVencido > 0;

  bool get isCreditExhausted =>
      creditLimit != null && creditLimit! > 0 && (availableCredit ?? 0) <= 0;

  bool get isLowCredit {
    if (creditLimit == null || creditLimit! <= 0 || availableCredit == null) return false;
    return (availableCredit! > 0) && (availableCredit! <= (creditLimit! * 0.20) || availableCredit! <= 100);
  }
}

class Category {
  final int id;
  final String name;

  Category({required this.id, required this.name});

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'Categoría',
    );
  }
}

class Product {
  final int id;
  final String name;
  final double price;
  final int stock;
  final double taxPercentage;
  final int? categoryId;
  final double discountPercentage;

  Product({
    required this.id,
    required this.name,
    required this.price,
    required this.stock,
    this.taxPercentage = 15.0,
    this.categoryId,
    this.discountPercentage = 0.0,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final rawStock = json['stock'];
    int parsedStock = 0;
    if (rawStock != null) {
      parsedStock = int.tryParse(rawStock.toString()) ?? (double.tryParse(rawStock.toString())?.toInt() ?? 0);
    }

    return Product(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'Producto',
      price: double.tryParse(json['price']?.toString() ?? '0') ?? 0.0,
      stock: parsedStock,
      taxPercentage: json['tax_percentage'] != null
          ? (double.tryParse(json['tax_percentage'].toString()) ?? 15.0)
          : 15.0,
      categoryId: json['category_id'] != null ? int.tryParse(json['category_id'].toString()) : null,
      discountPercentage: json['discount_percentage'] != null
          ? (double.tryParse(json['discount_percentage'].toString()) ?? 0.0)
          : 0.0,
    );
  }
}

class Order {
  final int id;
  final int clientId;
  final String status;
  final double total;
  final DateTime createdAt;
  final DateTime? deliveryDate;
  final bool isInvoiced;
  final bool isPreinvoiced;
  final Client? client;

  Order({
    required this.id,
    required this.clientId,
    required this.status,
    required this.total,
    required this.createdAt,
    this.deliveryDate,
    this.isInvoiced = false,
    this.isPreinvoiced = false,
    this.client,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      clientId: int.tryParse(json['client_id']?.toString() ?? '0') ?? 0,
      status: json['status']?.toString() ?? 'pending',
      total: double.tryParse(json['total']?.toString() ?? '0') ?? 0.0,
      createdAt: json['created_at'] != null ? (DateTime.tryParse(json['created_at'].toString()) ?? DateTime.now()) : DateTime.now(),
      deliveryDate: json['delivery_date'] != null
          ? DateTime.tryParse(json['delivery_date'].toString())
          : null,
      isInvoiced: json['is_invoiced'] == true || json['is_invoiced'] == 1 || json['is_invoiced'] == '1',
      isPreinvoiced: json['is_preinvoiced'] == true || json['is_preinvoiced'] == 1 || json['is_preinvoiced'] == '1',
      client: json['client'] != null && json['client'] is Map<String, dynamic> ? Client.fromJson(json['client'] as Map<String, dynamic>) : null,
    );
  }
}
