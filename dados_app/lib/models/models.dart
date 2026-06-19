class Branch {
  final int id;
  final String name;
  final String? code;
  final String? address;
  final String? email;

  Branch({required this.id, required this.name, this.code, this.address, this.email});

  factory Branch.fromJson(Map<String, dynamic> json) {
    return Branch(
      id: json['id'],
      name: json['name'],
      code: json['code'],
      address: json['address'],
      email: json['email'],
    );
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
    this.branches = const [],
  });

  factory Client.fromJson(Map<String, dynamic> json) {
    return Client(
      id: json['id'],
      name: json['name'],
      email: json['email'],
      address: json['address'],
      identification: json['identification'],
      identificationType: json['identification_type'],
      nombreLocal: json['nombre_local'] ?? json['nombreLocal'],
      companyName: json['company_name'] ?? json['companyName'],
      branches: json['branches'] != null
          ? (json['branches'] as List).map((b) => Branch.fromJson(b)).toList()
          : [],
    );
  }
}

class Category {
  final int id;
  final String name;

  Category({required this.id, required this.name});

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: json['id'],
      name: json['name'],
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
    return Product(
      id: json['id'],
      name: json['name'],
      price: double.parse(json['price'].toString()),
      stock: json['stock'],
      taxPercentage: json['tax_percentage'] != null
          ? double.parse(json['tax_percentage'].toString())
          : 15.0,
      categoryId: json['category_id'],
      discountPercentage: json['discount_percentage'] != null
          ? double.parse(json['discount_percentage'].toString())
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
      id: json['id'],
      clientId: json['client_id'],
      status: json['status'],
      total: double.parse(json['total'].toString()),
      createdAt: DateTime.parse(json['created_at']),
      deliveryDate: json['delivery_date'] != null
          ? DateTime.parse(json['delivery_date'])
          : null,
      isInvoiced: json['is_invoiced'] == true || json['is_invoiced'] == 1,
      isPreinvoiced: json['is_preinvoiced'] == true || json['is_preinvoiced'] == 1,
      client: json['client'] != null ? Client.fromJson(json['client']) : null,
    );
  }
}
