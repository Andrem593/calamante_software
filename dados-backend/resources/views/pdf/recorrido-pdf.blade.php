<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Recorrido</title>
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
            margin-bottom: 25px;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-order {
            background-color: #fff7ed;
            color: #f97316;
            border: 1px solid #ffedd5;
        }
        .badge-visit {
            background-color: #ecfeff;
            color: #06b6d4;
            border: 1px solid #cffafe;
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
                        <div style="font-size: 24px; font-weight: bold;">Mapa y Recorrido de Ventas</div>
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
                        <div class="section-title">Información del Recorrido</div>
                        <div class="label">Período:</div>
                        <div class="value">{{ $fromStr }} al {{ $toStr }}</div>
                    </td>
                    <td>
                        <div class="section-title">Detalles del Vendedor</div>
                        <div class="label">Asesor Comercial:</div>
                        <div class="value">{{ $sellerName }}</div>
                    </td>
                </tr>
            </table>

            <div class="section-title">Resumen del Recorrido</div>
            <table class="summary-grid">
                <tr>
                    <td class="summary-card" style="width: 20%;">
                        <div class="summary-card-title">Total Visitas</div>
                        <div class="summary-card-value">{{ $totalVisits }}</div>
                    </td>
                    <td class="summary-card" style="width: 20%;">
                        <div class="summary-card-title">Pedidos Exitosos</div>
                        <div class="summary-card-value">{{ $totalOrders }}</div>
                    </td>
                    <td class="summary-card" style="width: 20%;">
                        <div class="summary-card-title">Ventas Totales</div>
                        <div class="summary-card-value">${{ number_format($totalAmount, 2) }}</div>
                    </td>
                    <td class="summary-card" style="width: 20%;">
                        <div class="summary-card-title">Crédito</div>
                        <div class="summary-card-value" style="color: #0284c7;">${{ number_format($totalCredit, 2) }}</div>
                    </td>
                    <td class="summary-card" style="width: 20%;">
                        <div class="summary-card-title">Contado</div>
                        <div class="summary-card-value" style="color: #16a34a;">${{ number_format($totalCash, 2) }}</div>
                    </td>
                </tr>
            </table>

            <div class="section-title">Mapa de Ruta</div>
            <div style="text-align: center; margin-bottom: 30px;">
                @if($mapBase64)
                    <img src="{{ $mapBase64 }}" style="width: 100%; border-radius: 12px; border: 1px solid #cbd5e1;">
                @else
                    <div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 40px; color: #94a3b8; font-size: 12px; text-align: center;">
                        No hay coordenadas disponibles para trazar el mapa de recorrido de este día.
                    </div>
                @endif
            </div>

            <div class="section-title">Cronología de Paradas</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 8%; text-align: center;">#</th>
                        <th style="width: 15%;">Hora</th>
                        <th style="width: 15%;">Tipo</th>
                        <th style="width: 32%;">Cliente</th>
                        <th style="width: 30%;">Detalles / Comentario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $index => $item)
                        <tr>
                            <td style="text-align: center; font-weight: bold; background-color: #f8fafc;">{{ $index + 1 }}</td>
                            <td style="font-weight: bold; color: #475569;">{{ Carbon\Carbon::parse($item['datetime'])->format('H:i') }}</td>
                            <td>
                                @if($item['type'] === 'order')
                                    <span class="badge badge-order">Pedido</span>
                                @else
                                    <span class="badge badge-visit">Visita</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: bold; color: #0f172a;">{{ $item['client'] }}</div>
                            </td>
                            <td style="color: #475569; font-style: italic; line-height: 1.4;">
                                {{ $item['comment'] ?: 'Sin comentarios registrados' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">
                                No se registraron paradas en el recorrido.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="footer">
                Documento de Recorrido de Ventas • Generado por Dados App
            </div>
        </div>
    </div>
</body>
</html>
