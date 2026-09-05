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

    public function appVersion()
    {
        return response()->json([
            'latest_version' => \App\Models\Setting::get('LATEST_APP_VERSION', '1.0.4'),
            'min_required_version' => \App\Models\Setting::get('MIN_REQUIRED_APP_VERSION', '1.0.4'),
            'apk_url' => \App\Models\Setting::get('APP_APK_URL', 'https://app.grupo-dados.com/downloads/dados-app.apk'),
        ]);
    }
}
