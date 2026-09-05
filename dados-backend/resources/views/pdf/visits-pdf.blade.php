<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Visitas y Ventas</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            background-color: white;
            width: 100%;
        }
        .header {
            background-color: #0f172a;
            color: white;
            padding: 30px 0;
            width: 100%;
        }
        .container {
            width: 520pt;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .content {
            padding: 30px 0;
        }
        .section-title {
            color: #94a3b8;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 5px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .info-grid td {
            width: 50%;
            vertical-align: top;
        }
        .label {
            color: #64748b;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .value {
            color: #0f172a;
            font-weight: bold;
            font-size: 14px;
        }
        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 30px;
            margin-left: -8px;
            margin-right: -8px;
        }
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .summary-card-title {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .summary-card-value {
            color: #0f172a;
            font-size: 16px;
            font-weight: bold;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
            padding: 10px;
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
        }
        .table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
            vertical-align: top;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('img/logo.jpeg');
        $logoSrc = '';
        if (file_exists($logoPath)) {
            $logoSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
        }
    @endphp

    <div class="header">
        <div class="container">
            <table class="header-table">
                <tr>
                    <td style="width: 75%; vertical-align: middle; text-align: left;">
                        <div style="font-size: 10px; color: #38bdf8; font-weight: bold; text-transform: uppercase; margin-bottom: 4px;">Reporte Ejecutivo</div>
                        <div style="font-size: 24px; font-weight: bold;">Visitas y Resumen de Ventas</div>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 10px;">Fecha de Generación: {{ date('d/m/Y H:i') }}</div>
                    </td>
                    <td style="width: 25%; text-align: right; vertical-align: middle;">
                        @if($logoSrc)
                            <img src="{{ $logoSrc }}" style="width: 60px; height: 60px; border-radius: 10px; margin-bottom: 5px;">
                        @endif
                        <div style="font-size: 12px; font-weight: bold; color: white;">Dados App</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="container">
        <div class="content">
            <table class="info-grid">
                <tr>
                    <td>
                        <div class="section-title">Filtros del Período</div>
                        <div class="label">Rango de Fechas:</div>
                        <div class="value">{{ $fromStr }} al {{ $toStr }}</div>
                    </td>
                    <td>
                        <div class="section-title">Filtros de Búsqueda</div>
                        <div class="label">Asesor Comercial:</div>
                        <div class="value">{{ $sellerName }}</div>
                        @if($search)
                            <div class="label" style="margin-top: 5px;">Búsqueda por Cliente:</div>
                            <div class="value" style="font-size: 12px; font-style: italic;">"{{ $search }}"</div>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="section-title">Resumen de Actividades y Ventas</div>
            <table class="summary-grid">
                <tr>
                    <td class="summary-card" style="width: 16%;">
                        <div class="summary-card-title">Visitas</div>
                        <div class="summary-card-value">{{ $totalVisits }}</div>
                    </td>
                    <td class="summary-card" style="width: 16%;">
                        <div class="summary-card-title">Pedidos</div>
                        <div class="summary-card-value">{{ $salesSummary['totalOrders'] }}</div>
                    </td>
                    <td class="summary-card" style="width: 20%;">
                        <div class="summary-card-title">Ventas Totales</div>
                        <div class="summary-card-value">${{ number_format($salesSummary['totalAmount'], 2) }}</div>
                    </td>
                    <td class="summary-card" style="width: 24%;">
                        <div class="summary-card-title">Factura</div>
                        <div class="summary-card-value" style="color: #16a34a;">${{ number_format($salesSummary['totalFacturaAmount'], 2) }}</div>
                        <div style="font-size: 8px; color: #64748b;">{{ $salesSummary['totalFacturaCount'] }} pedido(s)</div>
                    </td>
                    <td class="summary-card" style="width: 24%;">
                        <div class="summary-card-title">Prefactura</div>
                        <div class="summary-card-value" style="color: #d97706;">${{ number_format($salesSummary['totalPrefacturaAmount'], 2) }}</div>
                        <div style="font-size: 8px; color: #64748b;">{{ $salesSummary['totalPrefacturaCount'] }} pedido(s)</div>
                    </td>
                </tr>
            </table>

            <div class="section-title">Ventas por Producto</div>
            <table class="table" style="margin-bottom: 25px;">
                <thead>
                    <tr>
                        <th style="width: 35%;">Producto</th>
                        <th style="width: 15%;">Código</th>
                        <th style="width: 12%; text-align: center;">Cant. Total</th>
                        <th style="width: 18%; text-align: right;">Total ($)</th>
                        <th style="width: 20%; text-align: right;">Factura / Prefactura</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productSales as $prod)
                        <tr>
                            <td style="font-weight: bold; color: #0f172a;">{{ $prod['name'] }}</td>
                            <td style="color: #64748b;">{{ $prod['code'] }}</td>
                            <td style="text-align: center; font-weight: bold;">{{ $prod['total_qty'] }}</td>
                            <td style="text-align: right; font-weight: bold;">${{ number_format($prod['total_amount'], 2) }}</td>
                            <td style="text-align: right; font-size: 10px;">
                                <span style="color: #16a34a;">Fac: ${{ number_format($prod['invoiced_amount'], 2) }}</span><br>
                                <span style="color: #d97706;">Pre: ${{ number_format($prod['preinvoiced_amount'], 2) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 15px;">
                                No se registraron ventas de productos en el período seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="section-title">Detalle de Visitas</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Fecha / Hora</th>
                        <th style="width: 30%;">Cliente / Sucursal</th>
                        <th style="width: 20%;">Vendedor</th>
                        <th style="width: 30%;">Comentario / Notas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $visit)
                        <tr>
                            <td style="font-weight: bold;">{{ $visit->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div style="font-weight: bold; color: #0f172a;">{{ $visit->client?->name ?? 'Cliente Desconocido' }}</div>
                                @if($visit->branch)
                                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">Sucursal: {{ $visit->branch->name }}</div>
                                @endif
                            </td>
                            <td>{{ $visit->user?->name ?? '—' }}</td>
                            <td style="color: #475569; font-style: italic; line-height: 1.4;">
                                {{ $visit->comment ?: 'Sin comentarios registrados' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: #94a3b8; padding: 20px;">
                                No se registraron visitas en el período seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="footer">
                Documento de Reporte de Visitas • Generado por Dados App
            </div>
        </div>
    </div>
</body>
</html>
