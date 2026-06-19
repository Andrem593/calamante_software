import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'services/api_service.dart';
import 'services/auth_service.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/create_order_screen.dart';
import 'screens/review_order_screen.dart';
import 'screens/history_screen.dart';
import 'screens/order_detail_screen.dart';
import 'screens/profile_screen.dart';

void main() {
  debugPrint('--- APP STARTING ---');
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthService()),
        ProxyProvider<AuthService, ApiService>(
          update: (_, auth, __) => ApiService(auth.token),
        ),
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
  late Future<void> _authFuture;

  @override
  void initState() {
    super.initState();
    debugPrint('--- INICIANDO AUTO-LOGIN ---');
    _authFuture = Provider.of<AuthService>(context, listen: false)
        .tryAutoLogin()
        .timeout(const Duration(seconds: 5), onTimeout: () {
          debugPrint('--- AUTO-LOGIN TIMEOUT ---');
        });
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
      home: FutureBuilder(
        future: _authFuture,
        builder: (context, snapshot) {
          debugPrint('--- FUTUREBUILDER STATUS: ${snapshot.connectionState} ---');
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
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
        return null;
      },
    );
  }
}
