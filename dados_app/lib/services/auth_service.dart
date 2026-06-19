import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';

class AuthService with ChangeNotifier {
  String? _token;
  String? get token => _token;

  String? _loginError;
  String? get loginError => _loginError;

  Future<bool> login(String email, String password) async {
    _loginError = null;
    try {
      debugPrint('AuthService: Intentando iniciar sesión para $email...');
      debugPrint('AuthService: URL = ${ApiService.baseUrl}/login');
      final response = await http
          .post(
            Uri.parse('${ApiService.baseUrl}/login'),
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            body: jsonEncode({'email': email, 'password': password}),
          )
          .timeout(const Duration(seconds: 10));

      debugPrint(
          'AuthService: Respuesta de la API recibida. Código de estado: ${response.statusCode}');
      debugPrint('AuthService: Cuerpo de la respuesta: ${response.body}');

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _token = data['access_token'];

        // Guardar token persistentemente
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _token!);

        notifyListeners();
        return true;
      } else {
        try {
          final data = jsonDecode(response.body);
          _loginError = data['message'] ??
              'Error de inicio de sesión (${response.statusCode})';
        } catch (_) {
          _loginError = 'Error del servidor (${response.statusCode})';
        }
        return false;
      }
    } catch (e, stackTrace) {
      debugPrint('AuthService: Error/Excepción en login: $e');
      debugPrint('AuthService: StackTrace: $stackTrace');
      _loginError = 'Error de conexión: $e';
      return false;
    }
  }

  Future<void> tryAutoLogin() async {
    if (_token != null) return; // Ya está logueado en memoria
    final prefs = await SharedPreferences.getInstance();
    if (prefs.containsKey('auth_token')) {
      _token = prefs.getString('auth_token');
      notifyListeners();
    }
  }

  Future<bool> logout() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    notifyListeners();
    return true;
  }

  Future<Map<String, dynamic>> changePassword(
      String currentPassword, String newPassword) async {
    try {
      final response = await http.post(
        Uri.parse('${ApiService.baseUrl}/change-password'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer $_token',
        },
        body: jsonEncode({
          'current_password': currentPassword,
          'new_password': newPassword,
          'new_password_confirmation': newPassword,
        }),
      );

      final data = jsonDecode(response.body);
      if (response.statusCode == 200) {
        return {'success': true, 'message': data['message']};
      }
      return {
        'success': false,
        'message': data['message'] ?? 'Error al cambiar contraseña'
      };
    } catch (e) {
      return {'success': false, 'message': 'Error de conexión: $e'};
    }
  }
}
