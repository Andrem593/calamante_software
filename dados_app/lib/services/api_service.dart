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

  void _handleErrors(http.Response response) {
    if (response.statusCode >= 400) {
      throw Exception('Error ${response.statusCode}: ${response.body}');
    }
  }
}
