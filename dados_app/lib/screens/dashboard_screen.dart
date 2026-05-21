import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import 'history_screen.dart';
import 'profile_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _stats;
  int _currentIndex = 0;
  String? _historyFilter;

  @override
  void initState() {
    super.initState();
    _loadStats();
  }

  Future<void> _loadStats() async {
    debugPrint('Dashboard: Iniciando carga de stats');
    try {
      final data = await context.read<ApiService>().get('/dashboard');
      debugPrint('Dashboard: Datos recibidos con éxito');
      if (!mounted) return;
      setState(() {
        _stats = data;
        debugPrint('Dashboard: setState completado');
      });
    } catch (e) {
      debugPrint('Dashboard Error: $e');
    }
  }

  Widget _buildHome() {
    return _stats == null
        ? const Center(child: CircularProgressIndicator())
        : SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Buscador
                TextField(
                  decoration: InputDecoration(
                    hintText: 'Buscar productos',
                    prefixIcon: const Icon(Icons.search),
                    fillColor: const Color(0xFFF1FBFE),
                    filled: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                const Text('Pedidos Activos',
                    style:
                        TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                        child: _buildStatCard(
                            'En proceso',
                            _stats?['pending_orders']?.toString() ?? '0',
                            false)),
                    const SizedBox(width: 16),
                    Expanded(
                        child: _buildStatCard('Pedidos Mes',
                            _stats?['month_orders']?.toString() ?? '0', false)),
                  ],
                ),
                const SizedBox(height: 16),
                _buildStatCard(
                    'Ventas Mes',
                    '\$${(double.tryParse(_stats?['month_sales']?.toString() ?? '0') ?? 0.0).toStringAsFixed(0)}',
                    true),
                const SizedBox(height: 32),
                // Pedido Urgente Section
                const Text('Entregas de Hoy',
                    style:
                        TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                const SizedBox(height: 12),
                _buildTodayDeliveriesCard(),
                const SizedBox(height: 32),
                const Text('Últimos Pedidos',
                    style:
                        TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                const SizedBox(height: 16),
                ...((_stats?['recent_orders'] as List?) ?? []).map((order) {
                  return _buildOrderTile(order);
                }).toList(),
              ],
            ),
          );
  }

  @override
  Widget build(BuildContext context) {
    debugPrint('Dashboard: Building state _currentIndex=$_currentIndex');
    Widget body;
    try {
      switch (_currentIndex) {
        case 0:
          body = _buildHome();
          break;
        case 2:
          body = HistoryScreen(initialFilter: _historyFilter);
          break;
        case 3:
          body = const ProfileScreen();
          break;
        default:
          body = _buildHome();
      }
    } catch (e) {
      debugPrint('CRASH en build de Dashboard: $e');
      body = Center(child: Text('Error al renderizar: $e'));
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: _currentIndex == 0
          ? AppBar(
              title: const Text('Hielo purificado',
                  style: TextStyle(fontWeight: FontWeight.bold)),
              backgroundColor: Colors.white,
              elevation: 0,
              actions: [
                IconButton(
                    icon: const Icon(Icons.shopping_cart_outlined),
                    onPressed: () {}),
              ],
            )
          : null,
      body: body,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) {
          if (index == 1) {
            Navigator.pushNamed(context, '/create_order')
                .then((_) => _loadStats());
          } else {
            setState(() {
              _currentIndex = index;
              if (index == 2) {
                _historyFilter =
                    'Todos'; // Reset filter when clicking tab directly
              }
            });
          }
        },
        type: BottomNavigationBarType.fixed,
        selectedItemColor: const Color(0xFF03A9F4),
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Inicio'),
          BottomNavigationBarItem(
              icon: Icon(Icons.assignment_outlined), label: 'Pedido'),
          BottomNavigationBarItem(
              icon: Icon(Icons.history), label: 'Historial'),
          BottomNavigationBarItem(
              icon: Icon(Icons.person_outline), label: 'Perfil'),
        ],
      ),
      floatingActionButton: _currentIndex == 0
          ? FloatingActionButton.extended(
              onPressed: () async {
                await Navigator.pushNamed(context, '/create_order');
                _loadStats();
              },
              backgroundColor: const Color(0xFF03A9F4),
              label: const Text('Nuevo Pedido',
                  style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.bold)),
            )
          : null,
    );
  }

  Widget _buildStatCard(String title, String value, bool isFullWidth) {
    return Container(
      width: isFullWidth ? double.infinity : null,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFF1FBFE),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE1F5FE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: const TextStyle(
                  fontWeight: FontWeight.w500, color: Colors.grey)),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(value,
                style:
                    const TextStyle(fontSize: 28, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildTodayDeliveriesCard() {
    int count = _stats?['today_deliveries'] ?? 0;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: const LinearGradient(
          colors: [Color(0xFF03A9F4), Color(0xFF0288D1)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF03A9F4).withOpacity(0.3),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Icon(Icons.local_shipping, color: Colors.white, size: 32),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Text('Programadas',
                      style: TextStyle(color: Colors.white, fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text('$count Entregas',
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.bold)),
            const Text('pendientes para hoy',
                style: TextStyle(color: Colors.white70, fontSize: 14)),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () {
                setState(() {
                  _historyFilter = 'Entregas Hoy';
                  _currentIndex = 2; // Switch to History tab
                });
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: const Color(0xFF03A9F4),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
                padding:
                    const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('Ver Detalles',
                      style: TextStyle(fontWeight: FontWeight.bold)),
                  SizedBox(width: 8),
                  Icon(Icons.arrow_forward, size: 16),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderTile(dynamic order) {
    String status = order['status'] ?? 'pending';
    Color statusColor = Colors.orange;
    if (status == 'delivered') statusColor = Colors.green;
    if (status == 'invoiced' || status == 'on_the_way')
      statusColor = Colors.blue;

    return ListTile(
      onTap: () =>
          Navigator.pushNamed(context, '/order_detail', arguments: order['id']),
      leading: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
            color: const Color(0xFFF1FBFE),
            borderRadius: BorderRadius.circular(12)),
        child: Icon(Icons.receipt_outlined, color: statusColor),
      ),
      title: Text('Pedido #${order['id']}',
          style: const TextStyle(fontWeight: FontWeight.bold)),
      subtitle: Text(
          (order['client']?['nombre_local'] ?? order['client']?['company_name'] ?? order['client']?['name'] ?? 'Cliente N/A').toString(),
          style: const TextStyle(color: Colors.grey),
          overflow: TextOverflow.ellipsis),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
              '\$${(double.tryParse(order['total']?.toString() ?? '0') ?? 0.0).toStringAsFixed(2)}',
              style: const TextStyle(fontWeight: FontWeight.bold)),
          Text(
              order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1
                  ? 'PREFACTURADO'
                  : status == 'pending'
                      ? 'EN PROCESO'
                      : status == 'delivered'
                          ? 'ENTREGADO'
                          : status == 'invoiced'
                              ? 'FACTURADO'
                              : status == 'on_the_way'
                                  ? 'EN CAMINO'
                                  : status.toUpperCase(),
              style: TextStyle(
                  color: (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? Colors.blue : statusColor,
                  fontSize: 10,
                  fontWeight: FontWeight.bold)),
        ],
      ),
      contentPadding: EdgeInsets.zero,
    );
  }
}
