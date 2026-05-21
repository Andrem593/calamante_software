<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->seller) {
            $query->where('user_id', $request->seller);
        }
        if ($request->search) {
            $query->where('id', 'like', "%{$request->search}%")
                  ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$request->search}%"));
        }

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'date', 'seller', 'search']),
        ]);
    }

    public function map()
    {
        $today = Carbon::today();

        $orders = Order::with(['client', 'branch', 'user'])
            ->whereDate('delivery_date', $today)
            ->whereNotNull('latitude')
            ->orWhereHas('branch', fn ($q) => $q->whereNotNull('latitude'))
            ->get()
            ->map(function ($order) {
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
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['client', 'branch', 'user', 'items.product']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order
        ]);
    }

    public function exportPdf(Order $order)
    {
        $order->load(['client', 'branch', 'user', 'items.product']);

        $pdf = Pdf::loadView('pdf.order-pdf', compact('order'));

        return $pdf->download("pedido-{$order->id}.pdf");
    }
}
