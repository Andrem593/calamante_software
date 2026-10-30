import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'dart:convert';
import 'services/api_service.dart';
import 'services/auth_service.dart';
import 'services/sync_service.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/create_order_screen.dart';
import 'screens/review_order_screen.dart';
import 'screens/history_screen.dart';
import 'screens/order_detail_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/visits_screen.dart';
import 'screens/create_visit_screen.dart';
import 'screens/branch_census_screen.dart';
import 'models/models.dart';

void main() {
  debugPrint('--- APP STARTING ---');
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthService()),
        ProxyProvider<AuthService, ApiService>(
          update: (_, auth, __) => ApiService(auth.token),
        ),
        ChangeNotifierProvider(create: (_) => SyncService()),
      ],
      child: const MyApp(),
    ),
  );
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  static const String appVersion = '1.0.12';
  late Future<Map<String, dynamic>?> _initFuture;
  bool _hasShownUpdateDialog = false;

  @override
  void initState() {
    super.initState();
    debugPrint('--- INICIANDO AUTO-LOGIN Y VERIFICACIÓN DE ACTUALIZACIÓN ---');
    _initFuture = _initializeApp();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final api = context.read<ApiService>();
      context.read<SyncService>().init(api);
    });
  }

  Future<Map<String, dynamic>?> _initializeApp() async {
    try {
      await Provider.of<AuthService>(context, listen: false)
          .tryAutoLogin()
          .timeout(const Duration(seconds: 5));
    } catch (e) {
      debugPrint('Auto-login error or timeout: $e');
    }

    try {
      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/app-version'),
        headers: {'Accept': 'application/json'},
      ).timeout(const Duration(seconds: 4));

      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
    } catch (e) {
      debugPrint('Version check error: $e');
    }
    return null;
  }

  bool _isVersionOlder(String current, String required) {
    List<int> currParts = current.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    List<int> reqParts = required.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    
    for (int i = 0; i < 3; i++) {
      int currVal = i < currParts.length ? currParts[i] : 0;
      int reqVal = i < reqParts.length ? reqParts[i] : 0;
      if (currVal < reqVal) return true;
      if (currVal > reqVal) return false;
    }
    return false;
  }

  void _showRecommendedUpdateDialog(BuildContext context, String latestVersion, String apkUrl) {
    if (_hasShownUpdateDialog) return;
    _hasShownUpdateDialog = true;
    
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.system_update_alt, color: Color(0xFF03A9F4)),
            SizedBox(width: 10),
            Text('Actualización Disponible', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          ],
        ),
        content: Text(
          'Hay una nueva versión disponible ($latestVersion). ¿Deseas descargarla ahora?',
          style: const TextStyle(fontSize: 15),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Más tarde', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              if (apkUrl.isNotEmpty) {
                final uri = Uri.parse(apkUrl);
                if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('No se pudo abrir el enlace de actualización')),
                  );
                }
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF03A9F4),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: const Text('Actualizar'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Dados App',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF03A9F4),
          primary: const Color(0xFF03A9F4),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.grey[100],
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide.none,
          ),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        ),
        useMaterial3: true,
      ),
      home: FutureBuilder<Map<String, dynamic>?>(
        future: _initFuture,
        builder: (context, snapshot) {
          debugPrint('--- FUTUREBUILDER STATUS: ${snapshot.connectionState} ---');
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }

          final updateInfo = snapshot.data;
          if (updateInfo != null) {
            final latest = updateInfo['latest_version']?.toString() ?? '1.0.4';
            final minReq = updateInfo['min_required_version']?.toString() ?? '1.0.4';
            final apkUrl = updateInfo['apk_url']?.toString() ?? '';

            if (_isVersionOlder(appVersion, minReq)) {
              return UpdateRequiredScreen(apkUrl: apkUrl);
            }

            if (_isVersionOlder(appVersion, latest)) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                _showRecommendedUpdateDialog(context, latest, apkUrl);
              });
            }
          }

          return Consumer<AuthService>(
            builder: (context, auth, _) {
              if (auth.token != null) {
                return const DashboardScreen();
              } else {
                return const LoginScreen();
              }
            },
          );
        },
      ),
      onGenerateRoute: (settings) {
        if (settings.name == '/dashboard') {
          return MaterialPageRoute(builder: (_) => const DashboardScreen());
        }
        if (settings.name == '/create_order') {
          return MaterialPageRoute(builder: (_) => const CreateOrderScreen());
        }
        if (settings.name == '/review_order') {
          final args = settings.arguments;
          if (args is Map<String, dynamic>) {
            return MaterialPageRoute(
              builder: (_) => ReviewOrderScreen(
                client: args['client'],
                branch: args['branch'],
                items: List<Map<String, dynamic>>.from(args['items'] ?? []),
                products: args['products'],
                position: args['position'],
                notes: args['notes'] ?? '',
                address: args['address'],
                paymentMethod: args['payment_method'],
                creditDays: args['credit_days'],
                isDirectInvoice: args['is_direct_invoice'] ?? false,
                deliveryDate: args['delivery_date'],
              ),
            );
          }
        }
        if (settings.name == '/history') {
          return MaterialPageRoute(builder: (_) => const HistoryScreen());
        }
        if (settings.name == '/order_detail') {
          final orderId = int.tryParse(settings.arguments.toString()) ?? 0;
          return MaterialPageRoute(
              builder: (_) => OrderDetailScreen(orderId: orderId));
        }
        if (settings.name == '/profile') {
          return MaterialPageRoute(builder: (_) => const ProfileScreen());
        }
        if (settings.name == '/visits') {
          return MaterialPageRoute(builder: (_) => const VisitsScreen());
        }
        if (settings.name == '/create_visit') {
          return MaterialPageRoute(builder: (_) => const CreateVisitScreen());
        }
        if (settings.name == '/census' || settings.name == '/branch_census') {
          Client? clientArg;
          Branch? branchArg;
          if (settings.arguments is Map<String, dynamic>) {
            final args = settings.arguments as Map<String, dynamic>;
            clientArg = args['client'] as Client?;
            branchArg = args['branch'] as Branch?;
          } else if (settings.arguments is Client) {
            clientArg = settings.arguments as Client;
          }
          return MaterialPageRoute(
            builder: (_) => BranchCensusScreen(
              initialClient: clientArg,
              initialBranch: branchArg,
            ),
          );
        }
        return null;
      },
    );
  }
}

class UpdateRequiredScreen extends StatelessWidget {
  final String apkUrl;
  const UpdateRequiredScreen({super.key, required this.apkUrl});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(32.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: const BoxDecoration(
                  color: Color(0xFFFFF3E0),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.system_update_rounded,
                  size: 80,
                  color: Colors.orange,
                ),
              ),
              const SizedBox(height: 32),
              const Text(
                'Actualización Obligatoria',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Text(
                'Esta versión de la aplicación ya no está soportada. Para continuar usándola, debes actualizar a la versión más reciente.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey[600], fontSize: 15, height: 1.4),
              ),
              const SizedBox(height: 40),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  onPressed: () async {
                    if (apkUrl.isNotEmpty) {
                      final uri = Uri.parse(apkUrl);
                      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('No se pudo abrir el enlace de actualización')),
                        );
                      }
                    }
                  },
                  icon: const Icon(Icons.download, color: Colors.white),
                  label: const Text(
                    'Descargar Actualización',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF03A9F4),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
