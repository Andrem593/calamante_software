import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart';

class ApiService {
  final String? token;
  // Use 10.0.2.2 for Android Emulator, localhost for iOS simulator/Web
  static String get baseUrl {
    if (kIsWeb) {
      return 'http://localhost:8000/api';
    }
    // Para un dispositivo físico o emulador (si ambos están en la misma red), usamos la IP local de la PC
    if (defaultTargetPlatform == TargetPlatform.android) {
      return 'http://192.168.100.217:8000/api'; // Cambiado de 10.0.2.2 a la IP real
    }
    return 'http://192.168.100.217:8000/api';
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
