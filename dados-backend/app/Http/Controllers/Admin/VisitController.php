<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\User;
use App\Models\Order;
use App\Exports\VisitsReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class VisitController extends Controller
{
    private function getFilteredData(Request $request)
    {
        $from = null;
        $to = null;
        if ($request->has('from') || $request->has('to')) {
            $from = $request->from ? Carbon::parse($request->from)->startOfDay() : null;
            $to = $request->to ? Carbon::parse($request->to)->endOfDay() : null;
        } else {
            $from = Carbon::today()->startOfDay();
            $to = Carbon::today()->endOfDay();
        }

        // Query visits
        $visitsQuery = Visit::with(['client', 'user', 'branch'])->latest();

        if ($from && $to) {
            $visitsQuery->whereBetween('created_at', [$from, $to]);
        }

        if ($request->seller_id) {
            $visitsQuery->where('user_id', $request->seller_id);
        }

        if ($request->search) {
            $visitsQuery->whereHas('client', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        // Query orders with items and products
        $ordersQuery = Order::with(['items.product', 'client', 'user'])->where('status', '!=', 'cancelled');

        if ($from && $to) {
            $ordersQuery->whereBetween('created_at', [$from, $to]);
        }

        if ($request->seller_id) {
            $ordersQuery->where('user_id', $request->seller_id);
        }

        if ($request->search) {
            $ordersQuery->whereHas('client', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        return [$from, $to, $visitsQuery, $ordersQuery];
    }

    private function calculateSalesStatsAndProducts($orders)
    {
        $totalOrders = $orders->count();
        $totalAmount = (float) $orders->sum('total');

        $facturaOrders = $orders->filter(fn($o) => (bool)$o->is_invoiced);
        $totalFacturaAmount = (float) $facturaOrders->sum('total');
        $totalFacturaCount = $facturaOrders->count();

        $prefacturaOrders = $orders->filter(fn($o) => (bool)$o->is_preinvoiced && !$o->is_invoiced);
        $totalPrefacturaAmount = (float) $prefacturaOrders->sum('total');
        $totalPrefacturaCount = $prefacturaOrders->count();

        $totalCredit = (float) $orders->filter(function ($order) {
            $methodLower = strtolower($order->payment_method ?? '');
            return str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd');
        })->sum('total');

        $totalCash = max(0, $totalAmount - $totalCredit);

        $productSalesMap = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $prodId = $item->product_id;
                if (!isset($productSalesMap[$prodId])) {
                    $productSalesMap[$prodId] = [
                        'id' => $prodId,
                        'name' => $item->product ? $item->product->name : 'Producto Eliminado',
                        'code' => $item->product ? ($item->product->code ?? $item->product->sku ?? 'N/A') : 'N/A',
                        'total_qty' => 0,
                        'total_amount' => 0.0,
                        'invoiced_qty' => 0,
                        'invoiced_amount' => 0.0,
                        'preinvoiced_qty' => 0,
                        'preinvoiced_amount' => 0.0,
                    ];
                }

                $subtotal = (float)$item->subtotal;
                $qty = (int)$item->quantity;

                $productSalesMap[$prodId]['total_qty'] += $qty;
                $productSalesMap[$prodId]['total_amount'] += $subtotal;

                if ($order->is_invoiced) {
                    $productSalesMap[$prodId]['invoiced_qty'] += $qty;
                    $productSalesMap[$prodId]['invoiced_amount'] += $subtotal;
                } elseif ($order->is_preinvoiced) {
                    $productSalesMap[$prodId]['preinvoiced_qty'] += $qty;
                    $productSalesMap[$prodId]['preinvoiced_amount'] += $subtotal;
                }
            }
        }

        $productSales = array_values($productSalesMap);
        usort($productSales, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        return [
            'salesSummary' => [
                'totalOrders' => $totalOrders,
                'totalAmount' => round($totalAmount, 2),
                'totalFacturaAmount' => round($totalFacturaAmount, 2),
                'totalFacturaCount' => $totalFacturaCount,
                'totalPrefacturaAmount' => round($totalPrefacturaAmount, 2),
                'totalPrefacturaCount' => $totalPrefacturaCount,
                'totalCredit' => round($totalCredit, 2),
                'totalCash' => round($totalCash, 2),
            ],
            'productSales' => $productSales,
        ];
    }

    public function index(Request $request)
    {
        [$from, $to, $visitsQuery, $ordersQuery] = $this->getFilteredData($request);

        $sellers = User::where('role', 'seller')->orderBy('name')->get(['id', 'name']);

        $visits = $visitsQuery->paginate(20)->withQueryString();
        $visits->appends([
            'from' => $from ? $from->toDateString() : '',
            'to' => $to ? $to->toDateString() : '',
            'seller_id' => $request->seller_id ?? '',
            'search' => $request->search ?? '',
        ]);

        $orders = $ordersQuery->get();
        $salesData = $this->calculateSalesStatsAndProducts($orders);

        return Inertia::render('Admin/Visits/Index', [
            'visits' => $visits,
            'sellers' => $sellers,
            'salesSummary' => $salesData['salesSummary'],
            'productSales' => $salesData['productSales'],
            'filters' => [
                'from' => $from ? $from->toDateString() : null,
                'to' => $to ? $to->toDateString() : null,
                'seller_id' => $request->seller_id ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function map(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::today()->startOfDay();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::today()->endOfDay();
        $sellerId = $request->seller_id;

        $visitsQuery = Visit::with(['client.equipments', 'user', 'branch'])
            ->whereBetween('created_at', [$from, $to]);

        $ordersQuery = Order::with(['client.equipments', 'user'])
            ->whereBetween('created_at', [$from, $to]);

        if ($sellerId) {
            $visitsQuery->where('user_id', $sellerId);
            $ordersQuery->where('user_id', $sellerId);
        }

        $visits = $visitsQuery->get();
        $orders = $ordersQuery->get();

        $combined = collect();

        foreach ($visits as $visit) {
            $client = $visit->client;
            $clientName = $client ? ($client->nombre_local ?: ($client->company_name ?: $client->name)) : 'Cliente Desconocido';
            $equipmentsInfo = $client ? $client->equipments->map(function ($eq) {
                return "{$eq->model} ({$eq->capacity})";
            })->implode(', ') : '';

            $combined->push([
                'id' => 'visit_' . $visit->id,
                'type' => 'visit',
                'client' => $clientName . ($visit->branch ? " ({$visit->branch->name})" : ""),
                'seller' => $visit->user?->name,
                'comment' => $visit->comment,
                'lat' => $visit->latitude,
                'lng' => $visit->longitude,
                'time' => $visit->created_at->format('d/m/Y H:i'),
                'datetime' => $visit->created_at->toDateTimeString(),
                'equipments' => $equipmentsInfo ?: 'Ninguno',
            ]);
        }

        foreach ($orders as $order) {
            $client = $order->client;
            $clientName = $client ? ($client->nombre_local ?: ($client->company_name ?: $client->name)) : 'Cliente Desconocido';
            $equipmentsInfo = $client ? $client->equipments->map(function ($eq) {
                return "{$eq->model} ({$eq->capacity})";
            })->implode(', ') : '';

            $combined->push([
                'id' => 'order_' . $order->id,
                'type' => 'order',
                'client' => $clientName,
                'seller' => $order->user?->name,
                'comment' => 'Pedido #' . $order->id . ' — Total: $' . number_format($order->total, 2) . ($order->comment ? ' (' . $order->comment . ')' : ''),
                'lat' => $order->latitude,
                'lng' => $order->longitude,
                'time' => $order->created_at->format('d/m/Y H:i'),
                'datetime' => $order->created_at->toDateTimeString(),
                'equipments' => $equipmentsInfo ?: 'Ninguno',
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'status' => $order->status,
            ]);
        }

        $sortedVisits = $combined->sortBy('datetime')->values()->all();
        $sellers = User::where('role', 'seller')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Visits/Map', [
            'visits' => $sortedVisits,
            'sellers' => $sellers,
            'google_maps_api_key' => \App\Models\Setting::get('GOOGLE_MAPS_API_KEY') ?: env('GOOGLE_MAPS_API_KEY'),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'seller_id' => $sellerId ?? '',
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        [$from, $to, $visitsQuery, $ordersQuery] = $this->getFilteredData($request);

        $visits = $visitsQuery->get();
        $totalVisits = $visits->count();

        $orders = $ordersQuery->get();
        $salesData = $this->calculateSalesStatsAndProducts($orders);

        $fromStr = $from ? $from->format('d/m/Y') : 'Todos';
        $toStr = $to ? $to->format('d/m/Y') : 'Todos';

        $sellerName = 'Todos los vendedores';
        if ($request->seller_id) {
            $seller = User::find($request->seller_id);
            $sellerName = $seller ? $seller->name : 'N/A';
        }

        $search = $request->search;

        $pdf = Pdf::loadView('pdf.visits-pdf', [
            'visits' => $visits,
            'totalVisits' => $totalVisits,
            'salesSummary' => $salesData['salesSummary'],
            'productSales' => $salesData['productSales'],
            'fromStr' => $fromStr,
            'toStr' => $toStr,
            'sellerName' => $sellerName,
            'search' => $search,
        ]);

        return $pdf->stream('reporte-visitas.pdf');
    }

    public function exportExcel(Request $request)
    {
        [$from, $to, $visitsQuery, $ordersQuery] = $this->getFilteredData($request);

        $visits = $visitsQuery->get();
        $totalVisits = $visits->count();

        $orders = $ordersQuery->get();
        $salesData = $this->calculateSalesStatsAndProducts($orders);

        $fromStr = $from ? $from->format('d/m/Y') : 'Todos';
        $toStr = $to ? $to->format('d/m/Y') : 'Todos';

        $sellerName = 'Todos los vendedores';
        if ($request->seller_id) {
            $seller = User::find($request->seller_id);
            $sellerName = $seller ? $seller->name : 'N/A';
        }

        $search = $request->search;

        // Build single unified collection of visits and sales
        $combined = collect();

        foreach ($visits as $visit) {
            $notes = $visit->comment ?? '';
            if ($visit->latitude && $visit->longitude) {
                $gpsStr = "GPS: " . number_format($visit->latitude, 5) . ", " . number_format($visit->longitude, 5);
                $notes = $notes ? "{$notes} ({$gpsStr})" : $gpsStr;
            }

            $combined->push([
                'type' => 'Visita',
                'order_id' => '—',
                'created_at' => $visit->created_at,
                'datetime_str' => $visit->created_at ? $visit->created_at->format('d/m/Y H:i') : '',
                'seller' => $visit->user?->name ?? '—',
                'client' => $visit->client?->name ?? 'Cliente Desconocido',
                'local_name' => $visit->client?->nombre_local ?: '—',
                'ruc' => $visit->client?->identification ?? 'N/A',
                'branch' => $visit->branch?->name ?? 'Principal',
                'voucher_type' => '—',
                'payment_method' => '—',
                'items' => '—',
                'total_qty' => '—',
                'total' => '—',
                'notes' => $notes ?: 'Sin comentarios',
            ]);
        }

        foreach ($orders as $order) {
            $itemsStr = $order->items->map(fn($i) => ($i->product?->name ?? 'Producto') . " (x{$i->quantity})")->implode(', ');
            $totalQty = (int)$order->items->sum('quantity');
            $voucherType = $order->is_invoiced ? 'FACTURA' : ($order->is_preinvoiced ? 'PREFACTURA' : 'SIN FACTURAR');

            $combined->push([
                'type' => 'Venta',
                'order_id' => '#' . $order->id,
                'created_at' => $order->created_at,
                'datetime_str' => $order->created_at ? $order->created_at->format('d/m/Y H:i') : '',
                'seller' => $order->user?->name ?? '—',
                'client' => $order->client?->name ?? 'Cliente Desconocido',
                'local_name' => $order->client?->nombre_local ?: '—',
                'ruc' => $order->client?->identification ?? 'N/A',
                'branch' => $order->branch?->name ?? 'Principal',
                'voucher_type' => $voucherType,
                'payment_method' => $order->payment_method ?? 'Efectivo',
                'items' => $itemsStr,
                'total_qty' => $totalQty,
                'total' => '$' . number_format((float)$order->total, 2, '.', ''),
                'notes' => $order->comment ?: '—',
            ]);
        }

        $unifiedDetails = $combined->sortByDesc('created_at')->values()->all();

        $exportData = [
            'visits' => $visits,
            'orders' => $orders,
            'unifiedDetails' => $unifiedDetails,
            'totalVisits' => $totalVisits,
            'salesSummary' => $salesData['salesSummary'],
            'productSales' => $salesData['productSales'],
            'fromStr' => $fromStr,
            'toStr' => $toStr,
            'sellerName' => $sellerName,
            'search' => $search,
        ];

        $filename = 'Reporte_Visitas_Y_Ventas_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new VisitsReportExport($exportData), $filename);
    }

    public function exportMapPdf(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::today()->startOfDay();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::today()->endOfDay();
        $sellerId = $request->seller_id;

        $visitsQuery = Visit::with(['client', 'user', 'branch'])
            ->whereBetween('created_at', [$from, $to]);

        $ordersQuery = Order::with(['client', 'user'])
            ->whereBetween('created_at', [$from, $to]);

        if ($sellerId) {
            $visitsQuery->where('user_id', $sellerId);
            $ordersQuery->where('user_id', $sellerId);
        }

        $visits = $visitsQuery->get();
        $orders = $ordersQuery->get();

        $combined = collect();

        foreach ($visits as $visit) {
            $client = $visit->client;
            $clientName = $client ? ($client->nombre_local ?: ($client->company_name ?: $client->name)) : 'Cliente Desconocido';
            $combined->push([
                'id' => 'visit_' . $visit->id,
                'type' => 'visit',
                'client' => $clientName . ($visit->branch ? " ({$visit->branch->name})" : ""),
                'seller' => $visit->user?->name,
                'comment' => $visit->comment,
                'lat' => $visit->latitude,
                'lng' => $visit->longitude,
                'time' => $visit->created_at->format('d/m/Y H:i'),
                'datetime' => $visit->created_at->toDateTimeString(),
            ]);
        }

        foreach ($orders as $order) {
            $client = $order->client;
            $clientName = $client ? ($client->nombre_local ?: ($client->company_name ?: $client->name)) : 'Cliente Desconocido';
            $combined->push([
                'id' => 'order_' . $order->id,
                'type' => 'order',
                'client' => $clientName,
                'seller' => $order->user?->name,
                'comment' => 'Pedido #' . $order->id . ' — Total: $' . number_format($order->total, 2) . ($order->comment ? ' (' . $order->comment . ')' : ''),
                'lat' => $order->latitude,
                'lng' => $order->longitude,
                'time' => $order->created_at->format('d/m/Y H:i'),
                'datetime' => $order->created_at->toDateTimeString(),
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'status' => $order->status,
            ]);
        }

        $sortedVisits = $combined->sortBy('datetime')->values()->all();

        // Calculate statistics
        $totalVisits = count($sortedVisits);
        
        $activeOrders = $orders->where('status', '!=', 'cancelled');
        $totalOrders = $activeOrders->count();
        $totalAmount = $activeOrders->sum('total');

        $totalCredit = $activeOrders->filter(function ($order) {
            $methodLower = strtolower($order->payment_method ?? '');
            return str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd');
        })->sum('total');

        $totalCash = $totalAmount - $totalCredit;

        // Generate Static Map URL
        $pathPoints = [];
        $visitMarkers = [];
        $orderMarkers = [];
        foreach ($sortedVisits as $item) {
            if ($item['lat'] && $item['lng']) {
                $latLng = "{$item['lat']},{$item['lng']}";
                $pathPoints[] = $latLng;
                
                if ($item['type'] === 'order') {
                    $orderMarkers[] = $latLng;
                } else {
                    $visitMarkers[] = $latLng;
                }
            }
        }

        $mapBase64 = $request->map_image ?: '';
        if (!$mapBase64 && count($pathPoints) > 0) {
            $markersParams = [];
            if (count($visitMarkers) > 0) {
                $markersParams[] = "markers=color:cyan|" . implode('|', $visitMarkers);
            }
            if (count($orderMarkers) > 0) {
                $markersParams[] = "markers=color:orange|" . implode('|', $orderMarkers);
            }

            $pathString = "path=color:0x0ea5e9|weight:4|" . implode('|', $pathPoints);
            $markersString = implode('&', $markersParams);
            $apiKey = \App\Models\Setting::get('GOOGLE_MAPS_API_KEY') ?? env('GOOGLE_MAPS_API_KEY');
            $staticMapUrl = "https://maps.googleapis.com/maps/api/staticmap?size=600x350&maptype=roadmap&{$pathString}&{$markersString}&key={$apiKey}";
            
            try {
                // Fetch static map and base64-encode it (bypass SSL verify checks on local server)
                $context = stream_context_create([
                    "ssl" => [
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ],
                ]);
                $mapData = file_get_contents($staticMapUrl, false, $context);
                if ($mapData) {
                    $mapBase64 = 'data:image/png;base64,' . base64_encode($mapData);
                }
            } catch (\Exception $e) {
                \Log::error("Failed fetching static map: " . $e->getMessage());
            }
        }

        // Metadatos
        $fromStr = $from->format('d/m/Y');
        $toStr = $to->format('d/m/Y');

        $sellerName = 'Todos los vendedores';
        if ($sellerId) {
            $seller = User::find($sellerId);
            $sellerName = $seller ? $seller->name : 'N/A';
        }

        $pdf = Pdf::loadView('pdf.recorrido-pdf', [
            'visits' => $sortedVisits,
            'totalVisits' => $totalVisits,
            'totalOrders' => $totalOrders,
            'totalAmount' => $totalAmount,
            'totalCredit' => $totalCredit,
            'totalCash' => $totalCash,
            'mapBase64' => $mapBase64,
            'fromStr' => $fromStr,
            'toStr' => $toStr,
            'sellerName' => $sellerName
        ]);

        return $pdf->stream("recorrido-{$sellerName}-" . $from->format('d-m-Y') . ".pdf");
    }

    public function destroy(Visit $visit)
    {
        $visit->delete();
        return redirect()->back()->with('success', 'Visita eliminada exitosamente.');
    }
}
