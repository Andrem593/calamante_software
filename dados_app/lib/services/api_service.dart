import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart';

class ApiService {
  final String? token;
  // Permite sobreescribir la URL por consola al compilar usando: --dart-define=API_URL=https://tu-url.com/api
  static const String _envApiUrl = String.fromEnvironment('API_URL');

  // Use 10.0.2.2 for Android Emulator, localhost for iOS simulator/Web
  static String get baseUrl {
    if (_envApiUrl.isNotEmpty) {
      return _envApiUrl;
    }
    // Entorno Local:
    // return 'http://10.0.2.2:8000/api'; // O 'http://192.168.100.217:8000/api'
    // Producción:
    return 'https://app.grupo-dados.com/api';
  }

  ApiService(this.token);

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String endpoint) async {
    debugPrint('ApiService GET: $baseUrl$endpoint');
    try {
      final response = await http
          .get(Uri.parse('$baseUrl$endpoint'), headers: _headers)
          .timeout(const Duration(seconds: 10));
      debugPrint('ApiService status: ${response.statusCode}');
      _handleErrors(response);
      return jsonDecode(response.body);
    } catch (e) {
      debugPrint('ApiService error GET: $e');
      rethrow;
    }
  }

  Future<dynamic> post(String endpoint, Map<String, dynamic> body) async {
    debugPrint('ApiService POST: $baseUrl$endpoint');
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$endpoint'),
            headers: _headers,
            body: jsonEncode(body),
          )
          .timeout(const Duration(seconds: 10));
      debugPrint('ApiService status: ${response.statusCode}');
      _handleErrors(response);
      return jsonDecode(response.body);
    } catch (e) {
      debugPrint('ApiService error POST: $e');
      rethrow;
    }
  }

  Future<List<dynamic>> getEquipments() async {
    final response = await get('/equipments');
    return response as List<dynamic>;
  }

  Future<List<dynamic>> getClientEquipments(int clientId,
      {int? branchId}) async {
    final String url = branchId != null
        ? '/clients/$clientId/equipments?branch_id=$branchId'
        : '/clients/$clientId/equipments?branch_id=null';
    final response = await get(url);
    return response as List<dynamic>;
  }

  Future<void> syncClientEquipments(int clientId, List<int> equipmentIds,
      {int? branchId}) async {
    await post('/clients/$clientId/equipments', {
      'equipment_ids': equipmentIds,
      'branch_id': branchId,
    });
  }

  Future<dynamic> getClientCreditStatus(int clientId) async {
    return await get('/clients/$clientId/credit-status');
  }

  void _handleErrors(http.Response response) {
    if (response.statusCode >= 400) {
      throw Exception('Error ${response.statusCode}: ${response.body}');
    }
  }
}
