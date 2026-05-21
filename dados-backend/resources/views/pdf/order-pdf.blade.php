<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #{{ $order->id }}</title>
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
            padding: 40px 0;
            width: 100%;
        }
        /* Contenedor principal con ancho fijo para evitar que DomPDF se salga de la hoja */
        .container {
            width: 520pt;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .status-badge {
            margin-top: 10px;
            padding: 5px 12px;
            background-color: #0ea5e9;
            color: white;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 6px;
            display: inline-block;
        }
        .content {
            padding: 40px 0;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        .info-grid td {
            width: 50%;
            vertical-align: top;
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
        .label {
            color: #64748b;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .value {
            color: #0f172a;
            font-weight: bold;
            font-size: 15px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
            padding: 12px;
            font-size: 11px;
            text-transform: uppercase;
            color: #475569;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .total-row td {
            background-color: #f8fafc;
            border-top: 2px solid #e2e8f0;
            font-weight: 800;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
        }
        .signature-box {
            display: inline-block;
            text-align: center;
            width: 250px;
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }
        .signature-img {
            max-height: 100px;
            max-width: 200px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    @php
        $statusTranslations = [
            'pending' => 'Pendiente',
            'invoiced' => 'Facturado',
            'on_the_way' => 'En Camino',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ];
        $statusSpanish = $statusTranslations[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status));

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
                    <td style="width: 70%; vertical-align: middle; text-align: left;">
                        <div style="font-size: 10px; color: #38bdf8; font-weight: bold; text-transform: uppercase; margin-bottom: 4px;">Comprobante de Pedido</div>
                        <div style="font-size: 28px; font-weight: bold;">Pedido #{{ $order->id }}</div>
                        <div class="status-badge">{{ $statusSpanish }}</div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 15px;">Fecha: {{ $order->created_at->format('d/m/Y') }}</div>
                    </td>
                    <td style="width: 30%; text-align: right; vertical-align: middle;">
                        @if($logoSrc)
                            <img src="{{ $logoSrc }}" style="width: 70px; height: 70px; border-radius: 12px; margin-bottom: 5px;">
                        @endif
                        <div style="font-size: 14px; font-weight: bold; color: white;">Dados App</div>
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
                        <div class="section-title">Información del Cliente</div>
                        <div class="label">Cliente:</div>
                        <div class="value">{{ $order->client?->name ?? 'Cliente Genérico' }}</div>
                    <div class="label" style="margin-top: 5px;">Sucursal:</div>
                    <div style="font-size: 13px; font-weight: bold; color: #475569;">{{ $order->branch?->name ?? 'Principal' }}</div>
                    <div class="label" style="margin-top: 5px;">Dirección:</div>
                    <div style="font-size: 12px; color: #64748b; font-style: italic;">{{ $order->address }}</div>
                </td>
                <td>
                    <div class="section-title">Detalles del Vendedor</div>
                    <div class="label">Asesor Comercial:</div>
                    <div class="value">{{ $order->user?->name ?? 'Sistema' }}</div>
                    <div style="margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 10px;">
                        <span class="label">Forma de Pago:</span> <span style="color: #1e293b; font-weight: bold; font-size: 13px;">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</span><br>
                        <span class="label">Fecha Entrega:</span> <span style="color: #1e293b; font-weight: bold; font-size: 13px;">{{ $order->delivery_date ? $order->delivery_date->format('d/m/Y') : '—' }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section-title">Detalle de Productos</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th style="text-align: right;">Precio Unitario</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;">{{ $item->product?->name ?? 'Producto' }}</td>
                        <td style="text-align: center; color: #475569;">{{ $item->quantity }}</td>
                        <td style="text-align: right; color: #475569;">${{ number_format($item->price, 2) }}</td>
                        <td style="text-align: right; font-weight: bold; color: #0f172a;">${{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 11px; color: #64748b; letter-spacing: 0.1em;">Total General</td>
                    <td style="text-align: right; font-size: 20px; color: #0f172a;">${{ number_format($order->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        @if($order->notes)
            <div class="section-title">Observaciones</div>
            <div style="font-size: 12px; font-style: italic; color: #475569; background-color: #f8fafc; padding: 15px; border-radius: 10px; border-left: 4px solid #e2e8f0; margin-bottom: 40px;">
                {{ $order->notes }}
            </div>
        @endif

        <div class="footer">
            <div class="signature-box">
                @if($order->signature)
                    <img src="{{ $order->signature }}" class="signature-img">
                @else
                    <div style="height: 100px;"></div>
                @endif
                <div style="border-top: 2px solid #e2e8f0; padding-top: 10px;">
                    <div style="font-size: 14px; font-weight: bold; color: #0f172a;">{{ $order->requested_by_name ?? 'Nombre no disponible' }}</div>
                    <div style="font-size: 12px; color: #64748b;">{{ $order->requested_by_id }}</div>
                    <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-top: 5px; letter-spacing: 0.1em;">Firma Digital del Solicitante</div>
                </div>
            </div>
            
            <div style="margin-top: 50px; font-size: 10px; color: #adb5bd; text-transform: uppercase; letter-spacing: 0.2em;">
                Documento Identificado: Pedido #{{ $order->id }} • Dados App
            </div>
        </div>
    </div>
    </div>
</body>
</html>
