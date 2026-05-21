import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? _userData;
  Map<String, dynamic>? _stats;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    final api = context.read<ApiService>();
    try {
      final user = await api.get('/user');
      final dashboard = await api.get('/dashboard');

      if (!mounted) return;

      setState(() {
        _userData = user as Map<String, dynamic>;
        _stats = dashboard as Map<String, dynamic>;
        _loading = false;
      });
    } catch (e) {
      print(e);
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  Future<void> _contactSupport() async {
    final whatsappUrl = Uri.parse(
        "https://wa.me/593967402331?text=Hola, necesito soporte con la App Calamante.");
    if (!await launchUrl(whatsappUrl, mode: LaunchMode.externalApplication)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo abrir WhatsApp')),
      );
    }
  }

  void _showChangePasswordDialog() {
    final currentPassCtrl = TextEditingController();
    final newPassCtrl = TextEditingController();
    final confirmPassCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cambiar Contraseña'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: currentPassCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Contraseña Actual'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: newPassCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Nueva Contraseña'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: confirmPassCtrl,
              obscureText: true,
              decoration:
                  const InputDecoration(labelText: 'Confirmar Contraseña'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () async {
              if (newPassCtrl.text != confirmPassCtrl.text) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Las contraseñas no coinciden')),
                );
                return;
              }
              final result = await context
                  .read<AuthService>()
                  .changePassword(currentPassCtrl.text, newPassCtrl.text);

              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(result['message']),
                  backgroundColor:
                      result['success'] ? Colors.green : Colors.red,
                ),
              );
            },
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF1FBFE),
      appBar: AppBar(
        title: const Text('Mi Perfil',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: RefreshIndicator(
        onRefresh: _fetchData,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            children: [
              const SizedBox(height: 20),
              // Avatar Card
              Container(
                margin: const EdgeInsets.symmetric(horizontal: 20),
                padding: const EdgeInsets.all(25),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                        color: Colors.black.withOpacity(0.05),
                        blurRadius: 20,
                        offset: const Offset(0, 10))
                  ],
                ),
                child: Column(
                  children: [
                    const Stack(
                      alignment: Alignment.bottomRight,
                      children: [
                        CircleAvatar(
                          radius: 50,
                          backgroundColor: Color(0xFFE1F5FE),
                          child: Icon(Icons.person,
                              size: 60, color: Color(0xFF03A9F4)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 15),
                    Text(_userData?['name'] ?? 'Usuario',
                        style: const TextStyle(
                            fontSize: 22, fontWeight: FontWeight.bold)),
                    Text(_userData?['role'] ?? 'Vendedor',
                        style: const TextStyle(
                            color: Color(0xFF03A9F4),
                            fontWeight: FontWeight.w500)),
                    const Divider(height: 40),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _buildMiniStat(
                            '${_stats?['pending_orders'] ?? 0}', 'Pendientes'),
                        _buildMiniDivider(),
                        _buildMiniStat(
                            '${_stats?['month_orders'] ?? 0}', 'Pedidos Mes'),
                        _buildMiniDivider(),
                        _buildMiniStat(
                            '\$${(double.tryParse(_stats?['month_sales']?.toString() ?? '0') ?? 0.0).toStringAsFixed(0)}',
                            'Ventas Mes'),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 30),
              // Menu Items
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  children: [
                    _buildMenuItem(context, Icons.person_outline,
                        'Datos: ${_userData?['email'] ?? ''}'),
                    _buildMenuItem(context, Icons.lock_outline, 'Seguridad',
                        onTap: _showChangePasswordDialog),
                    _buildMenuItem(
                        context, Icons.help_outline, 'Ayuda (WhatsApp)',
                        onTap: _contactSupport),
                    const SizedBox(height: 20),
                    _buildMenuItem(context, Icons.logout, 'Cerrar Sesión',
                        isLogout: true, onTap: () async {
                      final auth = context.read<AuthService>();
                      await auth.logout();
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.remove('draft_client_id');
                      await prefs.remove('draft_cart');
                      await prefs.remove('draft_notes');
                      await prefs.remove('draft_address');
                      await prefs.remove('draft_payment_method');
                    }),
                  ],
                ),
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMiniStat(String value, String label) {
    return Column(
      children: [
        Text(value,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
        Text(label, style: const TextStyle(fontSize: 12, color: Colors.grey)),
      ],
    );
  }

  Widget _buildMiniDivider() {
    return Container(width: 1, height: 30, color: Colors.grey[200]);
  }

  Widget _buildMenuItem(BuildContext context, IconData icon, String label,
      {bool isLogout = false, VoidCallback? onTap}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: ListTile(
        leading:
            Icon(icon, color: isLogout ? Colors.red : const Color(0xFF03A9F4)),
        title: Text(label,
            style: TextStyle(
                fontWeight: FontWeight.w500,
                color: isLogout ? Colors.red : Colors.black)),
        trailing: const Icon(Icons.chevron_right, size: 20, color: Colors.grey),
        onTap: onTap,
      ),
    );
  }
}
