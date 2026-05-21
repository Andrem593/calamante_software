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
}
