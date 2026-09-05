<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
</head>
<body>
    <!-- Encabezado / Metadata -->
    <table>
        <tr>
            <td colspan="14" style="font-size: 16px; font-weight: bold; background-color: #0f172a; color: #ffffff; text-align: center;">
                REPORTE UNIFICADO DE VISITAS Y VENTAS
            </td>
        </tr>
        <tr>
            <td style="font-weight: bold; background-color: #f1f5f9;">Período:</td>
            <td colspan="5">{{ $fromStr }} al {{ $toStr }}</td>
            <td style="font-weight: bold; background-color: #f1f5f9;">Asesor Comercial:</td>
            <td colspan="7">{{ $sellerName }}</td>
        </tr>
        @if($search)
        <tr>
            <td style="font-weight: bold; background-color: #f1f5f9;">Búsqueda por Cliente:</td>
            <td colspan="13">{{ $search }}</td>
        </tr>
        @endif
        <tr>
            <td style="font-weight: bold; background-color: #f1f5f9;">Fecha de Generación:</td>
            <td colspan="13">{{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <br>

    <!-- Resumen Ejecutivo -->
    <table>
        <thead>
            <tr>
                <th colspan="7" style="font-size: 13px; font-weight: bold; background-color: #3b82f6; color: #ffffff;">
                    RESUMEN EJECUTIVO DE VENTAS Y ACTIVIDAD
                </th>
            </tr>
            <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
                <th>Total Visitas</th>
                <th>Total Pedidos</th>
                <th>Ventas Totales ($)</th>
                <th>Ventas Factura (Cant. / $)</th>
                <th>Ventas Prefactura (Cant. / $)</th>
                <th>Crédito Total ($)</th>
                <th>Contado Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <tr style="text-align: center;">
                <td>{{ $totalVisits }}</td>
                <td>{{ $salesSummary['totalOrders'] }}</td>
                <td style="font-weight: bold; color: #0f172a;">${{ number_format($salesSummary['totalAmount'], 2, '.', '') }}</td>
                <td style="color: #16a34a; font-weight: bold;">{{ $salesSummary['totalFacturaCount'] }} ped. — ${{ number_format($salesSummary['totalFacturaAmount'], 2, '.', '') }}</td>
                <td style="color: #d97706; font-weight: bold;">{{ $salesSummary['totalPrefacturaCount'] }} ped. — ${{ number_format($salesSummary['totalPrefacturaAmount'], 2, '.', '') }}</td>
                <td style="color: #0284c7;">${{ number_format($salesSummary['totalCredit'], 2, '.', '') }}</td>
                <td style="color: #16a34a;">${{ number_format($salesSummary['totalCash'], 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <br>

    <!-- Ventas por Productos -->
    <table>
        <thead>
            <tr>
                <th colspan="8" style="font-size: 13px; font-weight: bold; background-color: #0284c7; color: #ffffff;">
                    VENTAS POR PRODUCTO
                </th>
            </tr>
            <tr style="background-color: #cbd5e1; font-weight: bold;">
                <th>Producto</th>
                <th>Código / SKU</th>
                <th>Cant. Total</th>
                <th>Total Ventas ($)</th>
                <th>Cant. Factura</th>
                <th>Total Factura ($)</th>
                <th>Cant. Prefactura</th>
                <th>Total Prefactura ($)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($productSales as $prod)
                <tr>
                    <td style="font-weight: bold;">{{ $prod['name'] }}</td>
                    <td>{{ $prod['code'] }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $prod['total_qty'] }}</td>
                    <td style="text-align: right; font-weight: bold;">${{ number_format($prod['total_amount'], 2, '.', '') }}</td>
                    <td style="text-align: center; color: #16a34a;">{{ $prod['invoiced_qty'] }}</td>
                    <td style="text-align: right; color: #16a34a;">${{ number_format($prod['invoiced_amount'], 2, '.', '') }}</td>
                    <td style="text-align: center; color: #d97706;">{{ $prod['preinvoiced_qty'] }}</td>
                    <td style="text-align: right; color: #d97706;">${{ number_format($prod['preinvoiced_amount'], 2, '.', '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #94a3b8;">No se registraron ventas de productos en el período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <br>

    <!-- Detalle Unificado de Visitas y Ventas -->
    <table>
        <thead>
            <tr>
                <th colspan="14" style="font-size: 13px; font-weight: bold; background-color: #0f172a; color: #ffffff;">
                    DETALLE UNIFICADO DE VISITAS Y VENTAS
                </th>
            </tr>
            <tr style="background-color: #cbd5e1; font-weight: bold;">
                <th>Tipo</th>
                <th># Pedido</th>
                <th>Fecha y Hora</th>
                <th>Vendedor</th>
                <th>Cliente</th>
                <th>Nombre del Local</th>
                <th>RUC</th>
                <th>Sucursal</th>
                <th>Tipo de Comprobante</th>
                <th>Método de Pago</th>
                <th>Items Vendidos</th>
                <th>Cantidad Total</th>
                <th>Total</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($unifiedDetails as $row)
                <tr>
                    <td style="font-weight: bold; text-align: center; color: {{ $row['type'] === 'Venta' ? '#059669' : '#0284c7' }};">
                        {{ $row['type'] }}
                    </td>
                    <td style="font-weight: bold; text-align: center;">{{ $row['order_id'] }}</td>
                    <td>{{ $row['datetime_str'] }}</td>
                    <td>{{ $row['seller'] }}</td>
                    <td style="font-weight: bold;">{{ $row['client'] }}</td>
                    <td>{{ $row['local_name'] }}</td>
                    <td>{{ $row['ruc'] }}</td>
                    <td>{{ $row['branch'] }}</td>
                    <td style="text-align: center; font-weight: bold; color: {{ $row['voucher_type'] === 'FACTURA' ? '#16a34a' : ($row['voucher_type'] === 'PREFACTURA' ? '#d97706' : '#64748b') }};">
                        {{ $row['voucher_type'] }}
                    </td>
                    <td style="text-align: center;">{{ $row['payment_method'] }}</td>
                    <td>{{ $row['items'] }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $row['total_qty'] }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ $row['total'] }}</td>
                    <td>{{ $row['notes'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" style="text-align: center; color: #94a3b8;">No se registraron actividades (visitas ni ventas) en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
