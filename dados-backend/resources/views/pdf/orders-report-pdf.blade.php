<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Pedidos</title>
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
            padding: 25px 0;
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
            padding: 25px 0;
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
            margin-bottom: 20px;
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
            margin-bottom: 25px;
        }
        .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #cbd5e1;
            color: #475569;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 10px 8px;
            text-align: left;
        }
        .table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 8px;
            font-size: 11px;
            color: #334155;
            vertical-align: middle;
        }
        .table tr:nth-child(even) td {
            background-color: #f8fafc/50;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pending { background-color: #ffedd5; color: #ea580c; }
        .badge-delivered { background-color: #dcfce7; color: #16a34a; }
        .badge-cancelled { background-color: #fee2e2; color: #dc2626; }
        .badge-invoiced { background-color: #dbeafe; color: #2563eb; }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding: 20px 0;
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <table class="header-table">
                <tr>
                    <td>
                        <div style="font-size: 10px; color: #38bdf8; font-weight: bold; text-transform: uppercase; margin-bottom: 4px;">Reporte de Pedidos</div>
                        <div style="font-size: 20px; font-weight: bold; letter-spacing: -0.02em;">HISTORIAL Y RENDIMIENTO</div>
                    </td>
                    <td style="text-align: right; font-size: 24px; font-weight: 900; letter-spacing: -0.03em;">
                        DADOS
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="container content">
        <div class="section-title">Información General</div>
        <table class="info-grid">
            <tr>
                <td>
                    <div class="label">Vendedor</div>
                    <div class="value">{{ $user->name }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="label">Rango de Fechas</div>
                    <div class="value">
                        @if($start_date && $end_date)
                            {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
                        @else
                            Todo el historial
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <div class="section-title">Resumen de Ventas</div>
        <table class="summary-grid">
            <tr>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-card-title">Pedidos</div>
                    <div class="summary-card-value">{{ $orders->count() }}</div>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-card-title">Entregados</div>
                    <div class="summary-card-value">{{ $orders->where('status', 'delivered')->count() }}</div>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-card-title">Facturados</div>
                    <div class="summary-card-value">{{ $orders->where('is_invoiced', true)->count() }}</div>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-card-title">Total Ventas</div>
                    <div class="summary-card-value">${{ number_format($orders->where('status', '!=', 'cancelled')->sum('total'), 2) }}</div>
                </td>
            </tr>
        </table>

        <div class="section-title">Detalle de Pedidos</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 10%;">ID</th>
                    <th style="width: 15%;">Fecha</th>
                    <th style="width: 35%;">Cliente</th>
                    <th style="width: 15%; text-align: center;">Estado</th>
                    <th style="width: 13%; text-align: center;">Facturación</th>
                    <th style="width: 12%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td style="font-weight: bold;">#{{ $order->id }}</td>
                        <td>{{ $order->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div style="font-weight: bold; color: #0f172a;">
                                {{ $order->client?->nombre_local ?: ($order->client?->company_name ?: $order->client?->name) }}
                            </div>
                        </td>
                        <td style="text-align: center;">
                            @if($order->status === 'delivered')
                                <span class="badge badge-delivered">Entregado</span>
                            @elseif($order->status === 'cancelled')
                                <span class="badge badge-cancelled">Anulado</span>
                            @else
                                <span class="badge badge-pending">Pendiente</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @if($order->is_invoiced)
                                <span class="badge badge-invoiced">Factura</span>
                            @else
                                <span class="badge badge-pending">Prefactura</span>
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: bold; color: #0f172a;">
                            ${{ number_format($order->total, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">
                            No se encontraron pedidos en este rango de fechas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            Documento de Reporte de Pedidos • Generado por Dados App
        </div>
    </div>
</body>
</html>
