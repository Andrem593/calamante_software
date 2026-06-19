<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Client;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();

        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::today()->startOfDay();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::today()->endOfDay();

        $todayOrders = Order::whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled')->count();
        $todayDeliveries = Order::whereBetween('delivery_date', [$from->toDateString(), $to->toDateString()])->whereNotIn('status', ['delivered', 'cancelled'])->count();
        $pendingOrders = Order::whereIn('status', ['pending', 'invoiced', 'on_the_way'])->count();
        $todaySales = Order::whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled')->sum('total');

        $todayCreditSales = Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('payment_method', 'like', '%cred%')
                  ->orWhere('payment_method', 'like', '%créd%');
            })
            ->sum('total');
        $todayCashSales = $todaySales - $todayCreditSales;

        $salesByDay = Order::selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->where('status', '!=', 'cancelled')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $recentOrders = Order::with(['client', 'branch', 'user'])
            ->latest()
            ->take(10)
            ->get();

        $ordersByStatus = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        $topSellers = User::withCount(['orders as total_orders' => function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled');
            }])
            ->withSum(['orders as today_sales' => function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])->where('status', '!=', 'cancelled');
            }], 'total')
            ->orderByDesc('total_orders')
            ->take(5)
            ->get()
            ->map(function ($seller) use ($from, $to) {
                $orders = Order::where('user_id', $seller->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('status', '!=', 'cancelled')
                    ->get();

                $creditSales = $orders->filter(function ($order) {
                    $methodLower = strtolower($order->payment_method ?? '');
                    return str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd');
                })->sum('total');

                $totalSales = $orders->sum('total');
                $cashSales = $totalSales - $creditSales;

                $orderIds = $orders->pluck('id');
                $productBreakdown = \App\Models\OrderItem::with('product')
                    ->whereIn('order_id', $orderIds)
                    ->selectRaw('product_id, SUM(quantity) as quantity, SUM(subtotal) as total')
                    ->groupBy('product_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'product_name' => $item->product ? $item->product->name : 'Producto Eliminado',
                            'product_sku' => $item->product ? $item->product->sku : 'N/A',
                            'quantity' => (int)$item->quantity,
                            'total' => (float)$item->total,
                        ];
                    });

                $seller->today_cash_sales = $cashSales;
                $seller->today_credit_sales = $creditSales;
                $seller->product_breakdown = $productBreakdown;

                return $seller;
            });

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'today_orders' => $todayOrders,
                'today_deliveries' => $todayDeliveries,
                'pending_orders' => $pendingOrders,
                'today_sales' => $todaySales,
                'today_cash_sales' => $todayCashSales,
                'today_credit_sales' => $todayCreditSales,
                'total_clients' => Client::count(),
                'total_products' => Product::count(),
            ],
            'salesByDay' => $salesByDay,
            'recentOrders' => $recentOrders,
            'ordersByStatus' => $ordersByStatus,
            'topSellers' => $topSellers,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
