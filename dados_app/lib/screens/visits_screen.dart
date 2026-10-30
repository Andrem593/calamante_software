import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';

class VisitsScreen extends StatefulWidget {
  const VisitsScreen({super.key});

  @override
  State<VisitsScreen> createState() => _VisitsScreenState();
}

class _VisitsScreenState extends State<VisitsScreen> {
  List<dynamic> _visits = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadVisits();
  }

  Future<void> _loadVisits() async {
    if (!mounted) return;
    setState(() => _loading = true);
    try {
      final api = context.read<ApiService>();
      final data = await api.get('/visits');
      if (!mounted) return;
      setState(() => _visits = data as List);
    } catch (e) {
      debugPrint('VisitsScreen Error: $e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text(
          'Registro de Visitas',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.add_location_alt_outlined, color: Color(0xFF03A9F4)),
            tooltip: 'Censo de Sucursales',
            onPressed: () => Navigator.pushNamed(context, '/census'),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadVisits,
            tooltip: 'Actualizar',
          )
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _visits.isEmpty
              ? _buildEmptyState()
              : RefreshIndicator(
                  onRefresh: _loadVisits,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(20),
                    itemCount: _visits.length,
                    itemBuilder: (context, index) {
                      final visit = _visits[index];
                      return _buildVisitCard(visit);
                    },
                  ),
                ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.pushNamed(context, '/create_visit');
          if (result == true) {
            _loadVisits();
          }
        },
        backgroundColor: const Color(0xFF03A9F4),
        icon: const Icon(Icons.add_location_alt_outlined, color: Colors.white),
        label: const Text(
          'Nueva Visita',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: const Color(0xFFF1FBFE),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.location_off_outlined,
                size: 64,
                color: Color(0xFF03A9F4),
              ),
            ),
            const SizedBox(height: 24),
            const Text(
              'No hay visitas hoy',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Registra las visitas que realizas a tus clientes para realizar un seguimiento en el sistema.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600], fontSize: 14),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildVisitCard(dynamic visit) {
    final client = visit['client'] ?? {};
    final clientName = client['nombre_local'] ??
        client['company_name'] ??
        client['name'] ??
        'Cliente Desconocido';
    final branch = visit['branch'];
    final branchName = branch != null ? branch['name'] : null;
    final comment = visit['comment'] ?? 'Sin comentarios';
    final lat = visit['latitude'];
    final lng = visit['longitude'];

    String formattedDate = '';
    try {
      final parsedDate = DateTime.parse(visit['created_at']);
      formattedDate =
          DateFormat('dd/MM/yyyy hh:mm a').format(parsedDate.toLocal());
    } catch (_) {
      formattedDate = visit['created_at'] ?? '';
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey[100]!),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: () {},
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            clientName.toString(),
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                          if (branchName != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              'Sucursal: $branchName',
                              style: TextStyle(
                                color: Colors.grey[600],
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1FBFE),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'VISITA',
                        style: TextStyle(
                          color: Color(0xFF03A9F4),
                          fontSize: 9,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  formattedDate,
                  style: TextStyle(color: Colors.grey[500], fontSize: 11),
                ),
                const Divider(height: 20),
                Text(
                  comment.toString(),
                  style: TextStyle(color: Colors.grey[700], fontSize: 13),
                ),
                if (lat != null && lng != null) ...[
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Icon(
                        Icons.pin_drop,
                        color: Color(0xFF03A9F4),
                        size: 14,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${double.parse(lat.toString()).toStringAsFixed(6)}, ${double.parse(lng.toString()).toStringAsFixed(6)}',
                        style: TextStyle(
                          color: Colors.grey[500],
                          fontSize: 11,
                          fontFamily: 'monospace',
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
