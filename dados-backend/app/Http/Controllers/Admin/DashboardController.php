<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Client;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayDeliveries = Order::whereDate('delivery_date', $today)->where('status', '!=', 'delivered')->count();
        $pendingOrders = Order::whereIn('status', ['pending', 'invoiced', 'on_the_way'])->count();
        $todaySales = Order::whereDate('created_at', $today)->where('status', '!=', 'cancelled')->sum('total');

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

        $topSellers = User::withCount(['orders as total_orders' => function ($q) use ($today) {
                $q->whereDate('created_at', $today);
            }])
            ->withSum(['orders as today_sales' => function ($q) use ($today) {
                $q->whereDate('created_at', $today)->where('status', '!=', 'cancelled');
            }], 'total')
            ->orderByDesc('total_orders')
            ->take(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'today_orders' => $todayOrders,
                'today_deliveries' => $todayDeliveries,
                'pending_orders' => $pendingOrders,
                'today_sales' => $todaySales,
                'total_clients' => Client::count(),
                'total_products' => Product::count(),
            ],
            'salesByDay' => $salesByDay,
            'recentOrders' => $recentOrders,
            'ordersByStatus' => $ordersByStatus,
            'topSellers' => $topSellers,
        ]);
    }
}
