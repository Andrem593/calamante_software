<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        $months = collect(range(0, 5))->map(function ($i) {
            $date = Carbon::now()->subMonths($i);
            $total = Order::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->where('status', '!=', 'cancelled')
                ->sum('total');
            return ['month' => $date->format('M Y'), 'total' => $total];
        })->reverse()->values();

        $topSellers = User::withSum(['orders as total_sales' => function ($q) {
                $q->where('status', '!=', 'cancelled');
            }], 'total')
            ->withCount('orders')
            ->orderByDesc('total_sales')
            ->take(10)
            ->get();

        return Inertia::render('Admin/Reports/Index', [
            'monthlySales' => $months,
            'topSellers' => $topSellers,
        ]);
    }

    public function sales(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $sales = Order::with(['client', 'branch', 'user'])
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        $summary = [
            'total' => $sales->sum('total'),
            'count' => $sales->total(),
        ];

        return Inertia::render('Admin/Reports/Sales', [
            'sales' => $sales,
            'summary' => $summary,
            'filters' => $request->only(['from', 'to']),
        ]);
    }

    public function sellers(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::now()->endOfDay();

        $sellers = User::orderBy('name')
            ->get()
            ->map(function ($seller) use ($from, $to) {
                $orders = Order::where('user_id', $seller->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('status', '!=', 'cancelled')
                    ->get();

                $totalSales = $orders->sum('total');
                $ordersCount = $orders->count();

                $creditSales = $orders->filter(function ($order) {
                    $methodLower = strtolower($order->payment_method ?? '');
                    return str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd');
                })->sum('total');

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

                return [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'email' => $seller->email,
                    'total_orders' => $ordersCount,
                    'total_sales' => $totalSales,
                    'today_cash_sales' => $cashSales,
                    'today_credit_sales' => $creditSales,
                    'product_breakdown' => $productBreakdown,
                ];
            })
            ->sortByDesc('total_sales')
            ->values();

        $summary = [
            'total' => $sellers->sum('total_sales'),
            'orders' => $sellers->sum('total_orders'),
            'cash' => $sellers->sum('today_cash_sales'),
            'credit' => $sellers->sum('today_credit_sales'),
        ];

        return Inertia::render('Admin/Reports/Sellers', [
            'sellers' => $sellers,
            'summary' => $summary,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
