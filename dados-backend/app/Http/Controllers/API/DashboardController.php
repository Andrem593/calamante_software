<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $startOfMonth = now()->startOfMonth();
        $today = now()->format('Y-m-d');

        return response()->json([
            'pending_orders' => Order::where('user_id', $user->id)
                ->where('status', '!=', 'delivered')
                ->where('status', '!=', 'cancelled')
                ->count(),
            'month_orders' => Order::where('user_id', $user->id)
                ->where('created_at', '>=', $startOfMonth)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'month_sales' => Order::where('user_id', $user->id)
                ->where('created_at', '>=', $startOfMonth)
                ->where('status', '!=', 'cancelled')
                ->sum('total'),
            'today_deliveries' => Order::where('user_id', $user->id)
                ->where('delivery_date', $today)
                ->where('status', '!=', 'delivered')
                ->where('status', '!=', 'cancelled')
                ->count(),
            'recent_orders' => Order::where('user_id', $user->id)
                ->with('client')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
