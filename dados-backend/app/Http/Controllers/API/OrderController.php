<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
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
            'created_at'    => 'nullable|date',
            'branch_id'     => 'nullable|exists:branches,id,client_id,' . $request->client_id,
        ]);

        $client = \App\Models\Client::findOrFail($request->client_id);
        if ($client->isBlockedForSales()) {
            return response()->json([
                'error' => 'El cliente se encuentra BLOQUEADO por facturas vencidas. Contacte a un administrador para solicitar un desbloqueo temporal.',
                'is_blocked' => true,
            ], 422);
        }

        $branchId = $request->branch_id ?? null;
        $lockKey = "create_order_lock_{$request->user()->id}_{$request->client_id}_" . ($branchId ?: 'none');
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

        return $lock->block(5, function () use ($request, $branchId) {
            return DB::transaction(function () use ($request, $branchId) {
                $total = collect($request->items)->sum(function ($i) {
                    $discount = isset($i['discount_percentage']) ? (float)$i['discount_percentage'] : 0.0;
                    return $i['price'] * $i['quantity'] * (1 - $discount / 100);
                });
                $total = round($total, 4);

                $userId = $request->user()->id;
                $clientId = $request->client_id;

                // Conservar fecha original si viene de sincronización offline
                $createdAt = $request->filled('created_at')
                    ? \Carbon\Carbon::parse($request->created_at)
                    : now();

                // Prevención de duplicados por doble toque, reintentos de red o sincronización repetida (10 minutos por sucursal específica)
                $notes = $request->notes ?? null;
                $deliveryDate = $request->delivery_date ?? $createdAt->copy()->addDay()->format('Y-m-d');

                $recentDuplicateQuery = Order::where('user_id', $userId)
                    ->where('client_id', $clientId)
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($q) use ($createdAt) {
                        $q->where('created_at', '>=', now()->subMinutes(10))
                          ->orWhereBetween('created_at', [
                              $createdAt->copy()->subMinutes(5),
                              $createdAt->copy()->addMinutes(5)
                          ]);
                    });

                if ($branchId !== null) {
                    $recentDuplicateQuery->where('branch_id', $branchId);
                } else {
                    $recentDuplicateQuery->whereNull('branch_id');
                }

                $recentDuplicate = $recentDuplicateQuery->latest()
                    ->get()
                    ->first(function ($order) use ($total, $request, $notes, $deliveryDate) {
                        if (abs((float)$order->total - $total) > 0.01) {
                            return false;
                        }

                        if ($notes !== null && trim((string)$order->notes) !== trim((string)$notes)) {
                            return false;
                        }

                        if ($deliveryDate !== null && $order->delivery_date && \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d') !== \Carbon\Carbon::parse($deliveryDate)->format('Y-m-d')) {
                            return false;
                        }

                        $recentItems = $order->items()->pluck('quantity', 'product_id')->toArray();
                        $requestItems = collect($request->items)->pluck('quantity', 'product_id')->toArray();

                        ksort($recentItems);
                        ksort($requestItems);

                        return $recentItems == $requestItems;
                    });

                if ($recentDuplicate) {
                    \Illuminate\Support\Facades\Log::warning("Pedido duplicado evitado para usuario {$userId}, cliente {$clientId}, sucursal " . ($branchId ?? 'sin sucursal') . ", total {$total}. Retornando pedido original #{$recentDuplicate->id}");
                    return $recentDuplicate->load('items', 'client');
                }

                $isDirect = $request->boolean('is_direct_invoice', false);
                $client = Client::find($clientId);

                $paymentMethod = $request->payment_method;
                $isCredit = true;

                // Forzar forma de pago y días de crédito según configuración del cliente
                if ($client && $client->credit_days !== null && (int)$client->credit_days > 0) {
                    $paymentMethod = 'Crédito';
                    $creditDays = (int)$client->credit_days;
                    $isCredit = true;
                } elseif ($client && $client->credit_days !== null && (int)$client->credit_days === 0 && (empty($client->credit_limit) || (float)$client->credit_limit <= 0)) {
                    $paymentMethod = 'Efectivo';
                    $creditDays = 0;
                    $isCredit = false;
                } else {
                    if ($paymentMethod !== null) {
                        $methodLower = mb_strtolower($paymentMethod);
                        $isCredit = str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd');
                    }
                    $creditDays = $isCredit ? (int)($request->credit_days ?: 30) : 0;
                }

                $order = Order::create([
                    'user_id' => $userId,
                    'client_id' => $clientId,
                    'total' => $total,
                    'status' => 'pending',
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'notes' => $request->notes ?? null,
                    'address' => $request->address ?? null,
                    'payment_method' => $paymentMethod ?? null,
                    'credit_days' => $creditDays,
                    'requested_by_name' => $request->requested_by_name ?? null,
                    'requested_by_id' => $request->requested_by_id ?? null,
                    'signature'     => $request->signature ?? null,
                    'delivery_date' => $deliveryDate,
                    'branch_id'     => $request->branch_id ?? null,
                    'is_invoiced'   => $isDirect,
                    'is_preinvoiced'=> !$isDirect,
                    'created_at'    => $createdAt,
                    'updated_at'    => $createdAt,
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
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                OrderTracking::create([
                    'order_id' => $order->id,
                    'status' => 'created',
                    'details' => 'Pedido creado desde app móvil' . ($request->filled('created_at') ? ' (sincronizado)' : ''),
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Comprobar si la fecha del pedido es de un día anterior (fecha anterior a hoy)
                $isOlderDate = $createdAt->copy()->startOfDay()->lt(now()->startOfDay());

                if ($isOlderDate) {
                    \Illuminate\Support\Facades\Log::info("Pedido #{$order->id} con fecha anterior ({$createdAt->format('Y-m-d')}) guardado en backend pero retenido de envío automático a Contifico. Requiere aprobación administrativa.");
                    OrderTracking::create([
                        'order_id' => $order->id,
                        'status' => 'pending_contifico_approval',
                        'details' => 'Pedido con fecha anterior (' . $createdAt->format('d/m/Y') . '). Guardado en sistema; pendiente de autorización manual para emisión a Contifico.',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                } else {
                    // Intentar crear prefactura en Contifico automáticamente solo si es del día de hoy
                    try {
                        $contifico = app(\App\Services\ContificoService::class);
                        $contifico->createPreinvoice($order);
                    } catch (\Exception $e) {
                        \Log::error("Error al sincronizar con Contifico al crear pedido: " . $e->getMessage());
                    }
                }

                return $order->load('items', 'client');
            });
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

    public function getContificoDocument($contifico_id)
    {
        try {
            $contifico = app(\App\Services\ContificoService::class);
            $doc = $contifico->getDocument($contifico_id);
            if (!$doc) {
                return response()->json(['error' => 'No se pudo obtener el documento de Contifico.'], 404);
            }
            return response()->json($doc);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reportPdf(Request $request)
    {
        $tokenStr = $request->query('token');
        $user = null;
        if ($tokenStr) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenStr);
            if ($token) {
                $user = $token->tokenable;
            }
        }
        
        if (!$user) {
            $user = auth('sanctum')->user();
        }
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
        
        $query = Order::with(['client'])
            ->where('user_id', $user->id);
            
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        
        if ($request->filled('status') && $request->status !== 'Todos') {
            $status = $request->status;
            if ($status === 'Pendientes') {
                $query->whereIn('status', ['pending', 'processed']);
            } else if ($status === 'Completados') {
                $query->where('status', 'delivered');
            } else if ($status === 'Cancelados') {
                $query->where('status', 'cancelled');
            }
        }
        
        $orders = $query->latest()->get();
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orders-report-pdf', [
            'orders' => $orders,
            'user' => $user,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);
        
        return $pdf->stream('reporte-pedidos.pdf');
    }

    public function pdf(Request $request, $id)
    {
        $tokenStr = $request->query('token');
        $user = null;
        if ($tokenStr) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenStr);
            if ($token) {
                $user = $token->tokenable;
            }
        }
        if (!$user) {
            $user = auth('sanctum')->user();
        }
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $order = Order::with(['client', 'branch', 'user', 'items.product'])->findOrFail($id);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.order-pdf', compact('order'));
        return $pdf->stream("pedido-{$order->id}.pdf");
    }
}

