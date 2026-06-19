import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';

class HistoryScreen extends StatefulWidget {
  final String? initialFilter;
  const HistoryScreen({super.key, this.initialFilter});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  List<dynamic> _orders = [];
  bool _loading = true;
  late String _filter;

  @override
  void initState() {
    super.initState();
    _filter = widget.initialFilter ?? 'Todos';
    _loadOrders();
  }

  Future<void> _loadOrders() async {
    setState(() => _loading = true);
    try {
      final data = await context.read<ApiService>().get('/orders');
      setState(() => _orders = data as List);
    } catch (e) {
      print(e);
    } finally {
      setState(() => _loading = false);
    }
  }

  List<dynamic> get _filteredOrders {
    if (_filter == 'Todos') return _orders;
    if (_filter == 'Pendientes') {
      return _orders
          .where((o) => o['status'] == 'pending' || o['status'] == 'processed')
          .toList();
    }
    if (_filter == 'Completados') {
      return _orders.where((o) => o['status'] == 'delivered').toList();
    }
    if (_filter == 'Cancelados') {
      return _orders.where((o) => o['status'] == 'cancelled').toList();
    }
    if (_filter == 'Entregas Hoy') {
      final now = DateTime.now();
      return _orders.where((o) {
        if (o['delivery_date'] == null) return false;
        final d = DateTime.parse(o['delivery_date']);
        return d.year == now.year &&
            d.month == now.month &&
            d.day == now.day &&
            o['status'] != 'cancelled';
      }).toList();
    }
    return _orders;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Historial de Pedidos',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Buscar pedidos',
                prefixIcon: const Icon(Icons.search),
                fillColor: const Color(0xFFF1FBFE),
                filled: true,
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide.none),
              ),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 15),
            child: Row(
              children: [
                _buildFilterChip('Todos'),
                _buildFilterChip('Entregas Hoy'),
                _buildFilterChip('Pendientes'),
                _buildFilterChip('Completados'),
                _buildFilterChip('Cancelados'),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _filteredOrders.isEmpty
                    ? const Center(child: Text('No hay pedidos que mostrar'))
                    : ListView.builder(
                        padding: const EdgeInsets.all(20),
                        itemCount: _filteredOrders.length,
                        itemBuilder: (context, index) {
                          final order = _filteredOrders[index];
                          return _buildOrderCard(order);
                        },
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label) {
    bool isSelected = _filter == label;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 5),
      child: ChoiceChip(
        label: Text(label),
        selected: isSelected,
        onSelected: (val) => setState(() => _filter = label),
        selectedColor: const Color(0xFFE1F5FE),
        backgroundColor: Colors.white,
        labelStyle: TextStyle(
            color: isSelected ? const Color(0xFF03A9F4) : Colors.grey),
        side: BorderSide(
            color: isSelected ? const Color(0xFF03A9F4) : Colors.grey[200]!),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
    );
  }

  Widget _buildOrderCard(dynamic order) {
    String status = order['status'] ?? 'pending';
    String statusLabel = 'En proceso';
    Color statusColor = Colors.orange;

    if (status == 'cancelled') {
      statusLabel = 'Anulado';
      statusColor = Colors.red;
    } else if (status == 'delivered') {
      statusLabel = 'Entregado';
      statusColor = Colors.green;
    } else if (status == 'invoiced' || (order['is_invoiced'] == true || order['is_invoiced'] == 1)) {
      statusLabel = 'Facturado';
      statusColor = Colors.blue;
    } else if (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) {
      statusLabel = 'Prefacturado';
      statusColor = Colors.deepPurple;
    }

    // Lógica para "En camino"
    if (status != 'delivered' && status != 'cancelled') {
      final deliveryDateStr = order['delivery_date'];
      if (deliveryDateStr != null) {
        final deliveryDate = DateTime.parse(deliveryDateStr);
        final now = DateTime.now();
        if (deliveryDate.year == now.year &&
            deliveryDate.month == now.month &&
            deliveryDate.day == now.day) {
          statusLabel = 'En camino';
          statusColor = Colors.purple;
        }
      }
    }

    return GestureDetector(
      onTap: () =>
          Navigator.pushNamed(context, '/order_detail', arguments: order['id']),
      child: Container(
        margin: const EdgeInsets.only(bottom: 15),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey[100]!),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Pedido #${order['id']}',
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 16)),
                  Text('Cliente: ${(order['client']?['nombre_local'] ?? order['client']?['company_name'] ?? order['client']?['name'] ?? 'N/A')}',
                      style: const TextStyle(color: Colors.grey),
                      overflow: TextOverflow.ellipsis,
                      maxLines: 1),
                  if ((order['is_invoiced'] == true || order['is_invoiced'] == 1) || (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1))
                    Padding(
                      padding: const EdgeInsets.only(top: 4.0),
                      child: Row(
                        children: [
                          Icon(Icons.receipt_long,
                              size: 14, color: (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? Colors.purple : Colors.blue),
                          SizedBox(width: 4),
                          Text(
                              (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? 'Prefacturado' : 'Facturado',
                              style: TextStyle(
                                  fontSize: 12,
                                  color: (order['is_preinvoiced'] == true || order['is_preinvoiced'] == 1) ? Colors.purple : Colors.blue,
                                  fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: statusColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(statusLabel,
                  style: TextStyle(
                      color: statusColor,
                      fontWeight: FontWeight.bold,
                      fontSize: 12)),
            ),
          ],
        ),
      ),
    );
  }
}
