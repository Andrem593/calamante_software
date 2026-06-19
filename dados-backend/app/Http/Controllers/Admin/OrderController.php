<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['client', 'branch', 'user'])->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        $from = null;
        $to = null;
        if ($request->has('from') || $request->has('to')) {
            $from = $request->from ? Carbon::parse($request->from)->startOfDay() : null;
            $to = $request->to ? Carbon::parse($request->to)->endOfDay() : null;
        } else {
            // Default to today on initial load
            $from = Carbon::today()->startOfDay();
            $to = Carbon::today()->endOfDay();
        }

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        } elseif ($from) {
            $query->where('created_at', '>=', $from);
        } elseif ($to) {
            $query->where('created_at', '<=', $to);
        }

        if ($request->seller) {
            $query->where('user_id', $request->seller);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('id', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn ($subQ) => $subQ->where('name', 'like', "%{$request->search}%"));
            });
        }

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'status' => $request->status ?? '',
                'from' => $from ? $from->toDateString() : null,
                'to' => $to ? $to->toDateString() : null,
                'seller' => $request->seller ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function map(Request $request)
    {
        $from = null;
        $to = null;
        if ($request->has('from') || $request->has('to')) {
            $from = $request->from ? Carbon::parse($request->from)->toDateString() : null;
            $to = $request->to ? Carbon::parse($request->to)->toDateString() : null;
        } else {
            // Default to today on initial load
            $from = Carbon::today()->toDateString();
            $to = Carbon::today()->toDateString();
        }

        $query = Order::with(['client', 'branch', 'user']);

        if ($from && $to) {
            $query->whereBetween('delivery_date', [$from, $to]);
        } elseif ($from) {
            $query->where('delivery_date', '>=', $from);
        } elseif ($to) {
            $query->where('delivery_date', '<=', $to);
        }

        $query->where(function ($q) {
            $q->whereNotNull('latitude')
              ->orWhereHas('branch', fn ($subQ) => $subQ->whereNotNull('latitude'));
        });

        $orders = $query->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'status' => $order->status,
                'client' => $order->client?->name,
                'branch' => $order->branch?->name,
                'seller' => $order->user?->name,
                'total' => $order->total,
                'lat' => $order->latitude ?? $order->branch?->latitude,
                'lng' => $order->longitude ?? $order->branch?->longitude,
            ];
        });

        return Inertia::render('Admin/Orders/Map', [
            'orders' => $orders,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['client', 'branch', 'user', 'items.product']);

        $contificoStatus = null;
        if ($order->contifico_id) {
            try {
                $contifico = app(\App\Services\ContificoService::class);
                $statusData = $contifico->getDocumentStatus($order->contifico_id);
                $contificoStatus = $statusData['estado'] ?? null;
            } catch (\Exception $e) {
                \Log::error("Error loading Contifico status for Order #{$order->id}: " . $e->getMessage());
            }
        }

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'contifico_status' => $contificoStatus
        ]);
    }

    public function exportPdf(Order $order)
    {
        $order->load(['client', 'branch', 'user', 'items.product']);

        $pdf = Pdf::loadView('pdf.order-pdf', compact('order'));

        return $pdf->download("pedido-{$order->id}.pdf");
    }

    public function syncContifico(Order $order)
    {
        if ($order->status === 'cancelled') {
            return redirect()->back()->with('error', 'No se puede sincronizar un pedido anulado.');
        }

        try {
            $contifico = app(\App\Services\ContificoService::class);
            $result = $contifico->createPreinvoice($order);
            if ($result) {
                return redirect()->back()->with('success', 'Pedido sincronizado con Contifico exitosamente. ID de Prefactura: ' . ($order->contifico_id ?? 'N/A'));
            }
            return redirect()->back()->with('error', 'No se pudo sincronizar el pedido con Contifico.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al sincronizar con Contifico: ' . $e->getMessage());
        }
    }

    public function cancel(Order $order)
    {
        if ($order->status === 'cancelled') {
            return redirect()->back()->with('error', 'El pedido ya se encuentra anulado.');
        }

        $order->status = 'cancelled';
        $order->save();

        OrderTracking::create([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'details' => 'Pedido anulado desde el panel de administración',
        ]);

        return redirect()->back()->with('success', 'Pedido anulado exitosamente.');
    }

    public function authorizeSri(Order $order)
    {
        if ($order->status === 'cancelled') {
            return redirect()->back()->with('error', 'No se puede autorizar un pedido anulado.');
        }

        if (!$order->is_invoiced) {
            return redirect()->back()->with('error', 'Solo las facturas directas pueden ser autorizadas ante el SRI. Las pre-facturas no aplican para autorización electrónica.');
        }

        if (!$order->contifico_id) {
            return redirect()->back()->with('error', 'El pedido debe estar sincronizado con Contifico antes de ser enviado al SRI.');
        }

        try {
            $contifico = app(\App\Services\ContificoService::class);
            $result = $contifico->authorizeInvoiceSri($order->contifico_id);
            if ($result) {
                OrderTracking::create([
                    'order_id' => $order->id,
                    'status' => 'info',
                    'details' => 'Documento enviado a autorizar al SRI desde el panel de administración.',
                ]);
                return redirect()->back()->with('success', 'Documento enviado a autorizar al SRI exitosamente.');
            }
            return redirect()->back()->with('error', 'Contifico no pudo procesar la autorización del documento en el SRI.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al autorizar en el SRI: ' . $e->getMessage());
        }
    }

    public function edit(Order $order)
    {
        if ($order->status === 'delivered' || $order->status === 'cancelled' || $order->is_invoiced) {
            return redirect()->back()->with('error', 'Solo se pueden editar pedidos que sean pre-facturas en estado pendiente o en camino.');
        }

        $order->load(['client', 'branch', 'user', 'items.product']);
        $products = \App\Models\Product::where('status', 'A')
            ->where('is_visible', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'tax_percentage']);

        return Inertia::render('Admin/Orders/Edit', [
            'order' => $order,
            'products' => $products,
        ]);
    }

    public function update(Request $request, Order $order)
    {
        if ($order->status === 'delivered' || $order->status === 'cancelled' || $order->is_invoiced) {
            return redirect()->back()->with('error', 'Solo se pueden editar pedidos que sean pre-facturas en estado pendiente o en camino.');
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'delivery_date' => 'nullable|date',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order) {
            $total = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $discount = isset($item['discount_percentage']) ? (float)$item['discount_percentage'] : 0.0;
                $subtotal = (float)$item['price'] * (int)$item['quantity'] * (1 - $discount / 100);
                $total += $subtotal;
                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount_percentage' => $discount,
                    'subtotal' => $subtotal,
                ];
            }

            // Borrar items anteriores y crear los nuevos
            $order->items()->delete();

            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            $order->update([
                'total' => $total,
                'notes' => $request->notes ?? null,
                'delivery_date' => $request->delivery_date ?? $order->delivery_date,
            ]);

            OrderTracking::create([
                'order_id' => $order->id,
                'status' => 'info',
                'details' => 'Pedido editado desde el panel de administración',
            ]);
        });

        // Re-sincronizar con Contifico si ya tiene contifico_id
        $syncWarning = '';
        if ($order->contifico_id && !$order->is_invoiced) {
            try {
                $contifico = app(\App\Services\ContificoService::class);
                $contifico->createPreinvoice($order);
            } catch (\Exception $e) {
                \Log::error("Error syncing edited preinvoice #{$order->id} with Contifico: " . $e->getMessage());
                $syncWarning = ' El pedido se guardó localmente pero falló la sincronización con Contifico: ' . $e->getMessage();
            }
        }

        if ($syncWarning) {
            return redirect()->route('admin.orders.show', $order->id)->with('error', 'Pedido actualizado localmente.' . $syncWarning);
        }

        return redirect()->route('admin.orders.show', $order->id)->with('success', 'Pedido actualizado exitosamente y sincronizado con Contifico.');
    }

    public function bulkDeliver(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);

        $count = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $orders = Order::whereIn('id', $request->order_ids)->get();
            $processed = 0;

            foreach ($orders as $order) {
                if ($order->status !== 'delivered' && $order->status !== 'cancelled') {
                    $order->status = 'delivered';
                    $order->save();

                    OrderTracking::create([
                        'order_id' => $order->id,
                        'status' => 'delivered',
                        'details' => 'Pedido marcado como entregado en lote desde el panel de administración',
                    ]);
                    $processed++;
                }
            }
            return $processed;
        });

        return redirect()->back()->with('success', "{$count} pedidos marcados como entregados exitosamente.");
    }

    public function bulkPrint(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = explode(',', $request->ids);
        $orders = Order::with(['client', 'branch', 'user', 'items.product'])
            ->whereIn('id', $ids)
            ->get();

        return Inertia::render('Admin/Orders/BulkPrint', [
            'orders' => $orders,
        ]);
    }

    public function deliver(Order $order)
    {
        if ($order->status === 'delivered') {
            return redirect()->back()->with('error', 'El pedido ya se encuentra entregado.');
        }
        if ($order->status === 'cancelled') {
            return redirect()->back()->with('error', 'No se puede entregar un pedido anulado.');
        }

        $order->status = 'delivered';
        $order->save();

        OrderTracking::create([
            'order_id' => $order->id,
            'status' => 'delivered',
            'details' => 'Pedido marcado como entregado desde el panel de administración',
        ]);

        return redirect()->back()->with('success', 'Pedido marcado como entregado exitosamente.');
    }
}
