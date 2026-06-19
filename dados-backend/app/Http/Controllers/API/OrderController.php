<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return Order::with(['client' => function($query) {
                $query->select('id', 'name', 'nombre_local', 'company_name');
            }])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get(['id', 'client_id', 'total', 'status', 'is_invoiced', 'is_preinvoiced', 'delivery_date', 'created_at']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'credit_days' => 'nullable|integer|min:0',
            'is_direct_invoice' => 'nullable|boolean',
            'requested_by_name' => 'nullable|string',
            'requested_by_id' => 'nullable|string',
            'signature' => 'nullable|string',
            'delivery_date' => 'nullable|date',
            'branch_id'     => 'nullable|exists:branches,id',
        ]);

        return DB::transaction(function () use ($request) {
            $total = collect($request->items)->sum(function ($i) {
                $discount = isset($i['discount_percentage']) ? (float)$i['discount_percentage'] : 0.0;
                return $i['price'] * $i['quantity'] * (1 - $discount / 100);
            });

            $isDirect = $request->boolean('is_direct_invoice', false);

            $paymentMethod = $request->payment_method;
            $isCredit = true;
            if ($paymentMethod !== null) {
                $methodLower = mb_strtolower($paymentMethod);
                $isCredit = str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd');
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'client_id' => $request->client_id,
                'total' => $total,
                'status' => 'pending',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'notes' => $request->notes ?? null,
                'address' => $request->address ?? null,
                'payment_method' => $paymentMethod ?? null,
                'credit_days' => $isCredit ? (int)($request->credit_days ?: 30) : 0,
                'requested_by_name' => $request->requested_by_name ?? null,
                'requested_by_id' => $request->requested_by_id ?? null,
                'signature'     => $request->signature ?? null,
                'delivery_date' => $request->delivery_date ?? now()->addDay()->format('Y-m-d'),
                'branch_id'     => $request->branch_id ?? null,
                'is_invoiced'   => $isDirect,
                'is_preinvoiced'=> !$isDirect,
            ]);

            foreach ($request->items as $item) {
                $discount = isset($item['discount_percentage']) ? (float)$item['discount_percentage'] : 0.0;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount_percentage' => $discount,
                    'subtotal' => $item['price'] * $item['quantity'] * (1 - $discount / 100),
                ]);
            }

            OrderTracking::create([
                'order_id' => $order->id,
                'status' => 'created',
                'details' => 'Pedido creado desde app móvil',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // Intentar crear prefactura en Contifico
            try {
                $contifico = app(\App\Services\ContificoService::class);
                $contifico->createPreinvoice($order);
            } catch (\Exception $e) {
                \Log::error("Error al sincronizar con Contifico al crear pedido: " . $e->getMessage());
            }

            return $order->load('items', 'client');
        });
    }

    public function show($id)
    {
        return Order::with(['items.product', 'client', 'trackings'])->findOrFail($id);
    }

    public function markAsDelivered($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'delivered']);

        OrderTracking::create([
            'order_id' => $id,
            'status' => 'delivered',
            'details' => 'Pedido marcado como entregado desde app móvil',
        ]);

        return $order;
    }

    public function toggleInvoiced(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update(['is_invoiced' => $request->is_invoiced]);

        OrderTracking::create([
            'order_id' => $id,
            'status' => 'info',
            'details' => $request->is_invoiced ? 'Pedido marcado como facturado' : 'Pedido marcado como no facturado',
        ]);

        return $order;
    }
}
