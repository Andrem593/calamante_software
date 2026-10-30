<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Visit;
use App\Services\ContificoService;
use App\Services\GoogleGeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
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
            return ['month' => $date->format('M Y'), 'total' => (float)$total];
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

        $query = Order::with(['client', 'branch', 'user'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        // Filtro de estado (por defecto excluye cancelados a menos que se solicite)
        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'cancelled');
        }

        // Filtro de vendedor
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filtro por método de pago
        if ($request->payment_method) {
            $pm = strtolower($request->payment_method);
            if ($pm === 'credito') {
                $query->where(function($q) {
                    $q->where('payment_method', 'LIKE', '%cred%')
                      ->orWhere('payment_method', 'LIKE', '%créd%');
                });
            } elseif ($pm === 'efectivo') {
                $query->where(function($q) {
                    $q->where('payment_method', 'LIKE', '%efec%')
                      ->orWhereNull('payment_method');
                });
            } else {
                $query->where('payment_method', 'LIKE', "%{$request->payment_method}%");
            }
        }

        // Búsqueda por texto (Cliente, RUC, Sucursal, ID de pedido)
        if ($request->search) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $cleanId = ltrim(str_replace('#', '', $s), '0');
                if (is_numeric($cleanId)) {
                    $q->orWhere('id', (int)$cleanId);
                }
                $q->orWhereHas('client', function ($cq) use ($s) {
                    $cq->where('name', 'LIKE', "%{$s}%")
                       ->orWhere('nombre_local', 'LIKE', "%{$s}%")
                       ->orWhere('identification', 'LIKE', "%{$s}%");
                })->orWhereHas('branch', function ($bq) use ($s) {
                    $bq->where('name', 'LIKE', "%{$s}%");
                });
            });
        }

        // CÁLCULO TOTAL SOBRE LA CONSULTA COMPLETA (NO SOLO LA PÁGINA ACTUAL)
        $totalSales = (clone $query)->sum('total');
        $totalCount = (clone $query)->count();

        $cashSales = (clone $query)->where(function($q) {
            $q->where('payment_method', 'LIKE', '%efec%')
              ->orWhereNull('payment_method');
        })->sum('total');

        $creditSales = (clone $query)->where(function($q) {
            $q->where('payment_method', 'LIKE', '%cred%')
              ->orWhere('payment_method', 'LIKE', '%créd%');
        })->sum('total');

        $otherSales = max(0, $totalSales - ($cashSales + $creditSales));

        $invoicedCount = (clone $query)->where('is_invoiced', true)->count();
        $preinvoicedCount = (clone $query)->where('is_preinvoiced', true)->count();

        $sales = (clone $query)
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        $sellers = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Reports/Sales', [
            'sales' => $sales,
            'summary' => [
                'total' => round((float)$totalSales, 2),
                'count' => (int)$totalCount,
                'cash' => round((float)$cashSales, 2),
                'credit' => round((float)$creditSales, 2),
                'other' => round((float)$otherSales, 2),
                'invoiced_count' => (int)$invoicedCount,
                'preinvoiced_count' => (int)$preinvoicedCount,
            ],
            'sellers' => $sellers,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'user_id' => $request->user_id ?? '',
                'payment_method' => $request->payment_method ?? '',
                'status' => $request->status ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function exportSales(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $query = Order::with(['client', 'branch', 'user'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'cancelled');
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->payment_method) {
            $pm = strtolower($request->payment_method);
            if ($pm === 'credito') {
                $query->where(function($q) {
                    $q->where('payment_method', 'LIKE', '%cred%')
                      ->orWhere('payment_method', 'LIKE', '%créd%');
                });
            } elseif ($pm === 'efectivo') {
                $query->where(function($q) {
                    $q->where('payment_method', 'LIKE', '%efec%')
                      ->orWhereNull('payment_method');
                });
            } else {
                $query->where('payment_method', 'LIKE', "%{$request->payment_method}%");
            }
        }

        if ($request->search) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $cleanId = ltrim(str_replace('#', '', $s), '0');
                if (is_numeric($cleanId)) {
                    $q->orWhere('id', (int)$cleanId);
                }
                $q->orWhereHas('client', function ($cq) use ($s) {
                    $cq->where('name', 'LIKE', "%{$s}%")
                       ->orWhere('nombre_local', 'LIKE', "%{$s}%")
                       ->orWhere('identification', 'LIKE', "%{$s}%");
                })->orWhereHas('branch', function ($bq) use ($s) {
                    $bq->where('name', 'LIKE', "%{$s}%");
                });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $csvHeader = ["ID", "Fecha", "Vendedor", "Cliente", "RUC/Cedula", "Sucursal", "Metodo Pago", "Dias Credito", "Estado", "Prefacturado", "Facturado SRI", "Total ($)"];

        $filename = "Reporte_Ventas_" . date('Ymd_His') . ".csv";

        return response()->stream(function () use ($csvHeader, $orders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $csvHeader);

            foreach ($orders as $order) {
                fputcsv($file, [
                    '#' . $order->id,
                    $order->created_at->format('d/m/Y H:i'),
                    $order->user ? $order->user->name : 'N/A',
                    $order->client ? $order->client->name : 'N/A',
                    $order->client ? ($order->client->identification ?? 'N/A') : 'N/A',
                    $order->branch ? $order->branch->name : 'Principal',
                    $order->payment_method ?? 'Efectivo',
                    $order->credit_days ?? 0,
                    $order->status,
                    $order->is_preinvoiced ? 'SI' : 'NO',
                    $order->is_invoiced ? 'SI' : 'NO',
                    number_format((float)$order->total, 2, '.', ''),
                ]);
            }
            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function receivables(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->subMonths(3);
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $query = Order::with(['client', 'branch', 'user'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('payment_method', 'LIKE', '%cred%')
                  ->orWhere('payment_method', 'LIKE', '%créd%')
                  ->orWhere('credit_days', '>', 0);
            });

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->search) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->orWhereHas('client', function ($cq) use ($s) {
                    $cq->where('name', 'LIKE', "%{$s}%")
                       ->orWhere('nombre_local', 'LIKE', "%{$s}%")
                       ->orWhere('identification', 'LIKE', "%{$s}%");
                });
            });
        }

        $allOrders = (clone $query)->get();

        $today = Carbon::today();
        $processedOrders = $allOrders->map(function ($order) use ($today) {
            $creditDays = (int)($order->credit_days ?? $order->client?->credit_days ?? 30);
            $createdAt = Carbon::parse($order->created_at);
            $dueDate = $createdAt->copy()->addDays($creditDays);

            $isOverdue = $dueDate->lt($today);
            $daysOverdue = $isOverdue ? $today->diffInDays($dueDate) : 0;
            $daysRemaining = !$isOverdue ? $today->diffInDays($dueDate, false) * -1 : 0;

            return [
                'id' => $order->id,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'due_date' => $dueDate->format('Y-m-d'),
                'total' => (float)$order->total,
                'payment_method' => $order->payment_method ?? 'Crédito',
                'credit_days' => $creditDays,
                'is_overdue' => $isOverdue,
                'days_overdue' => (int)$daysOverdue,
                'days_remaining' => (int)$daysRemaining,
                'status' => $order->status,
                'client' => $order->client ? [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'nombre_local' => $order->client->nombre_local,
                    'identification' => $order->client->identification,
                    'phone' => $order->client->phone,
                    'credit_limit' => $order->client->credit_limit ? (float)$order->client->credit_limit : null,
                    'credit_days' => $order->client->credit_days ? (int)$order->client->credit_days : null,
                ] : null,
                'branch' => $order->branch ? ['name' => $order->branch->name] : null,
                'user' => $order->user ? ['name' => $order->user->name] : null,
            ];
        });

        if ($request->status === 'overdue') {
            $processedOrders = $processedOrders->filter(fn($item) => $item['is_overdue']);
        } elseif ($request->status === 'current') {
            $processedOrders = $processedOrders->filter(fn($item) => !$item['is_overdue']);
        }

        $totalReceivables = $processedOrders->sum('total');
        $overdueReceivables = $processedOrders->filter(fn($item) => $item['is_overdue'])->sum('total');
        $currentReceivables = $processedOrders->filter(fn($item) => !$item['is_overdue'])->sum('total');
        $overdueCount = $processedOrders->filter(fn($item) => $item['is_overdue'])->count();
        $uniqueClientsCount = $processedOrders->pluck('client.id')->unique()->filter()->count();

        $page = (int)($request->page ?? 1);
        $perPage = 30;
        $paginatedItems = $processedOrders->slice(($page - 1) * $perPage, $perPage)->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems,
            $processedOrders->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $sellers = User::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Reports/Receivables', [
            'orders' => $paginated,
            'summary' => [
                'total_receivables' => round((float)$totalReceivables, 2),
                'overdue_receivables' => round((float)$overdueReceivables, 2),
                'current_receivables' => round((float)$currentReceivables, 2),
                'overdue_count' => (int)$overdueCount,
                'clients_count' => (int)$uniqueClientsCount,
            ],
            'sellers' => $sellers,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'user_id' => $request->user_id ?? '',
                'status' => $request->status ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function exportReceivables(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->subMonths(3);
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $query = Order::with(['client', 'branch', 'user'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('payment_method', 'LIKE', '%cred%')
                  ->orWhere('payment_method', 'LIKE', '%créd%')
                  ->orWhere('credit_days', '>', 0);
            });

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->search) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->orWhereHas('client', function ($cq) use ($s) {
                    $cq->where('name', 'LIKE', "%{$s}%")
                       ->orWhere('nombre_local', 'LIKE', "%{$s}%")
                       ->orWhere('identification', 'LIKE', "%{$s}%");
                });
            });
        }

        $allOrders = $query->orderBy('created_at', 'desc')->get();
        $today = Carbon::today();

        $csvHeader = ["# Pedido", "Fecha Emision", "Fecha Vencimiento", "Cliente", "RUC/Cedula", "Vendedor", "Sucursal", "Dias Credito", "Cupo Cliente ($)", "Total Credito ($)", "Estado Cartera", "Dias Mora"];

        $filename = "Reporte_Cartera_" . date('Ymd_His') . ".csv";

        return response()->stream(function () use ($csvHeader, $allOrders, $today, $request) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $csvHeader);

            foreach ($allOrders as $order) {
                $creditDays = (int)($order->credit_days ?? $order->client?->credit_days ?? 30);
                $createdAt = Carbon::parse($order->created_at);
                $dueDate = $createdAt->copy()->addDays($creditDays);
                $isOverdue = $dueDate->lt($today);

                if ($request->status === 'overdue' && !$isOverdue) continue;
                if ($request->status === 'current' && $isOverdue) continue;

                $daysOverdue = $isOverdue ? $today->diffInDays($dueDate) : 0;
                $estadoMora = $isOverdue ? "VENCIDO ({$daysOverdue} dias)" : "VIGENTE (Al dia)";

                fputcsv($file, [
                    '#' . $order->id,
                    $order->created_at->format('d/m/Y H:i'),
                    $dueDate->format('d/m/Y'),
                    $order->client ? $order->client->name : 'N/A',
                    $order->client ? ($order->client->identification ?? 'N/A') : 'N/A',
                    $order->user ? $order->user->name : 'N/A',
                    $order->branch ? $order->branch->name : 'Principal',
                    $creditDays,
                    $order->client && $order->client->credit_limit ? number_format((float)$order->client->credit_limit, 2, '.', '') : 'N/A',
                    number_format((float)$order->total, 2, '.', ''),
                    $estadoMora,
                    $daysOverdue,
                ]);
            }
            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
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
                    'total_sales' => (float)$totalSales,
                    'today_cash_sales' => (float)$cashSales,
                    'today_credit_sales' => (float)$creditSales,
                    'product_breakdown' => $productBreakdown,
                ];
            })
            ->sortByDesc('total_sales')
            ->values();

        $summary = [
            'total' => round((float)$sellers->sum('total_sales'), 2),
            'orders' => (int)$sellers->sum('total_orders'),
            'cash' => round((float)$sellers->sum('today_cash_sales'), 2),
            'credit' => round((float)$sellers->sum('today_credit_sales'), 2),
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

    /**
     * Reporte 1: Clientes activos con promedio de facturación (últimos 6 meses).
     * Clasificados en rangos: 0-50, 50-100, 100-200, 200-500, 500-ADELANTE.
     * Excluye clientes con promedio 0.
     */
    public function activeClientsBilling(Request $request, ContificoService $contifico)
    {
        $isGenerated = $request->boolean('generate', false);

        if (!$isGenerated) {
            return Inertia::render('Admin/Reports/ActiveClientsBilling', [
                'generated' => false,
                'clients' => null,
                'summary' => null,
                'filters' => [
                    'months' => (int)($request->months ?? 6),
                    'from' => $request->from ?? '',
                    'to' => $request->to ?? '',
                    'range' => $request->range ?? '',
                    'search' => $request->search ?? '',
                ],
            ]);
        }

        $data = $this->getProcessedActiveClientsBilling($request, $contifico);
        $clientsCollection = $data['clients'];

        // Paginación
        $page = (int)($request->page ?? 1);
        $perPage = 30;
        $paginatedItems = $clientsCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $paginatedItems,
            $clientsCollection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Admin/Reports/ActiveClientsBilling', [
            'generated' => true,
            'clients' => $paginated,
            'summary' => $data['summary'],
            'filters' => [
                'from' => $data['from']->toDateString(),
                'to' => $data['to']->toDateString(),
                'months' => (int)$data['summary']['months_count'],
                'range' => $request->range ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function exportActiveClientsBilling(Request $request, ContificoService $contifico)
    {
        return $this->exportActiveClientsBillingExcel($request, $contifico);
    }

    public function exportActiveClientsBillingExcel(Request $request, ContificoService $contifico)
    {
        $data = $this->getProcessedActiveClientsBilling($request, $contifico);
        $clients = $data['clients'];
        $summary = $data['summary'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Promedio Facturación');
        $sheet->setShowGridLines(true);

        // 1. Título Principal
        $sheet->mergeCells('A1:J2');
        $sheet->setCellValue('A1', 'GRUPO DADOS - REPORTE DE PROMEDIO DE FACTURACIÓN DE CLIENTES ACTIVOS');
        $sheet->getStyle('A1:J2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Período Evaluado: ' . $data['from']->format('d/m/Y') . ' al ' . $data['to']->format('d/m/Y') . ' (' . $summary['months_count'] . ' Meses)');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

        $sheet->setCellValue('A4', 'Fecha de Emisión: ' . date('d/m/Y H:i:s'));
        $sheet->getStyle('A4')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

        // 2. Tarjetas KPI
        $cardHeaderStyle = [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        $cardValueStyle = [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '0284C7']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        $sheet->mergeCells('A6:C6');
        $sheet->setCellValue('A6', 'CLIENTES ACTIVOS');
        $sheet->getStyle('A6:C6')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('A7:C7');
        $sheet->setCellValue('A7', $summary['active_clients_count']);
        $sheet->getStyle('A7:C7')->applyFromArray($cardValueStyle);

        $sheet->mergeCells('D6:F6');
        $sheet->setCellValue('D6', 'TOTAL FACTURADO PERÍODO');
        $sheet->getStyle('D6:F6')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('D7:F7');
        $sheet->setCellValue('D7', (float)$summary['total_invoiced_all']);
        $sheet->getStyle('D7:F7')->applyFromArray($cardValueStyle);
        $sheet->getStyle('D7')->getNumberFormat()->setFormatCode('$#,##0.00');

        $sheet->mergeCells('G6:J6');
        $sheet->setCellValue('G6', 'PROMEDIO MENSUAL GENERAL');
        $sheet->getStyle('G6:J6')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('G7:J7');
        $sheet->setCellValue('G7', (float)$summary['overall_monthly_average']);
        $sheet->getStyle('G7:J7')->applyFromArray($cardValueStyle);
        $sheet->getStyle('G7')->getNumberFormat()->setFormatCode('$#,##0.00');

        // 3. Encabezados de Tabla
        $row = 9;
        $headers = [
            'A' => 'ID Cliente',
            'B' => 'Cliente',
            'C' => 'Local / Comercial',
            'D' => 'RUC / Cédula',
            'E' => 'Email',
            'F' => 'Teléfono',
            'G' => "Total Facturado ({$summary['months_count']}M)",
            'H' => 'Facturas Emitidas',
            'I' => 'Promedio Mensual ($)',
            'J' => 'Rango Facturación',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue($col . $row, $text);
        }

        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(25);

        // 4. Filas de Datos
        $row++;
        $startDataRow = $row;

        $rangeColors = [
            '0-50' => ['bg' => 'E2E8F0', 'fg' => '334155'],
            '50-100' => ['bg' => 'FEF3C7', 'fg' => '92400E'],
            '100-200' => ['bg' => 'DBEAFE', 'fg' => '1E40AF'],
            '200-500' => ['bg' => 'CFFAFE', 'fg' => '155E75'],
            '500-ADELANTE' => ['bg' => '10B981', 'fg' => 'FFFFFF'],
        ];

        foreach ($clients as $client) {
            $sheet->setCellValue('A' . $row, '#' . $client['id']);
            $sheet->setCellValue('B' . $row, $client['name']);
            $sheet->setCellValue('C' . $row, $client['nombre_local'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $client['identification'] ?? 'N/A');
            $sheet->setCellValue('E' . $row, $client['email'] ?? 'N/A');
            $sheet->setCellValue('F' . $row, $client['phone'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, (float)$client['total_invoiced']);
            $sheet->setCellValue('H' . $row, (int)$client['invoice_count']);
            $sheet->setCellValue('I' . $row, (float)$client['monthly_average']);
            $sheet->setCellValue('J' . $row, $client['range_label']);

            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');
            $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}:I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $zebra = ($row % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($zebra);

            $rKey = $client['range_key'] ?? '';
            if (isset($rangeColors[$rKey])) {
                $sheet->getStyle("J{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($rangeColors[$rKey]['bg']);
                $sheet->getStyle("J{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($rangeColors[$rKey]['fg']));
                $sheet->getStyle("J{$row}")->getFont()->setBold(true);
            }

            $sheet->getStyle("A{$row}:J{$row}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

            $row++;
        }

        // Totales
        $endDataRow = $row - 1;
        if ($endDataRow >= $startDataRow) {
            $sheet->setCellValue('A' . $row, 'TOTALES');
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue('G' . $row, "=SUM(G{$startDataRow}:G{$endDataRow})");
            $sheet->setCellValue('H' . $row, "=SUM(H{$startDataRow}:H{$endDataRow})");
            $sheet->setCellValue('I' . $row, "=SUM(I{$startDataRow}:I{$endDataRow})");

            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'borders' => [
                    'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']],
                    'bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']],
                ],
            ]);
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');
            $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Reporte_Facturacion_Clientes_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function getProcessedActiveClientsBilling(Request $request, ContificoService $contifico): array
    {
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::now()->endOfDay();

        if ($request->months && is_numeric($request->months) && (int)$request->months > 0) {
            $monthsCount = (int)$request->months;
            $from = $to->copy()->subMonths($monthsCount)->startOfDay();
        } else {
            $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::now()->subMonths(6)->startOfDay();
            $monthsCount = max(1, round($from->diffInMonths($to)));
            if ($monthsCount == 0) $monthsCount = 1;
        }

        // Cargar clientes locales con pedidos en el periodo
        $clients = Client::all();

        // 1. Facturas locales del periodo
        $localOrders = Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->get();

        $clientSalesMap = [];

        foreach ($localOrders as $order) {
            $clientId = $order->client_id;
            if (!$clientId) continue;

            if (!isset($clientSalesMap[$clientId])) {
                $clientSalesMap[$clientId] = [
                    'total' => 0.0,
                    'count' => 0,
                ];
            }
            $clientSalesMap[$clientId]['total'] += (float)$order->total;
            $clientSalesMap[$clientId]['count'] += 1;
        }

        // 2. Intentar consultar facturas de Contifico para enriquecer si se requiere
        try {
            $contificoInvoices = $contifico->fetchInvoices($from->format('d/m/Y'), $to->format('d/m/Y'));
            if (!empty($contificoInvoices) && is_array($contificoInvoices)) {
                $clientRucMap = $clients->keyBy('identification');
                $clientContificoMap = $clients->whereNotNull('contifico_id')->keyBy('contifico_id');

                foreach ($contificoInvoices as $inv) {
                    $docTotal = (float)($inv['total'] ?? 0);
                    if ($docTotal <= 0) continue;

                    // Buscar cliente correspondiente
                    $matchedClient = null;
                    if (!empty($inv['persona_id']) && isset($clientContificoMap[$inv['persona_id']])) {
                        $matchedClient = $clientContificoMap[$inv['persona_id']];
                    } elseif (!empty($inv['cliente']['cedula']) && isset($clientRucMap[$inv['cliente']['cedula']])) {
                        $matchedClient = $clientRucMap[$inv['cliente']['cedula']];
                    } elseif (!empty($inv['cliente']['ruc']) && isset($clientRucMap[$inv['cliente']['ruc']])) {
                        $matchedClient = $clientRucMap[$inv['cliente']['ruc']];
                    }

                    if ($matchedClient) {
                        $cId = $matchedClient->id;
                        // Si el cliente no tenía pedidos locales registrados, agregamos la factura de Contifico
                        if (!isset($clientSalesMap[$cId])) {
                            $clientSalesMap[$cId] = [
                                'total' => 0.0,
                                'count' => 0,
                            ];
                            $clientSalesMap[$cId]['total'] += $docTotal;
                            $clientSalesMap[$cId]['count'] += 1;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Could not fetch Contifico invoices for report: " . $e->getMessage());
        }

        $processed = collect();

        foreach ($clients as $client) {
            $sales = $clientSalesMap[$client->id] ?? null;
            $totalInvoiced = $sales ? (float)$sales['total'] : 0.0;
            $invoiceCount = $sales ? (int)$sales['count'] : 0;

            // Promedio mensual
            $monthlyAverage = round($totalInvoiced / $monthsCount, 2);

            // REGULA SOLICITADA: "si tiene 0 esos quitalos"
            if ($monthlyAverage <= 0 || $totalInvoiced <= 0) {
                continue;
            }

            // Asignación de rangos según promedio de facturación
            if ($monthlyAverage <= 50) {
                $rangeKey = '0-50';
                $rangeLabel = '$0 - $50';
            } elseif ($monthlyAverage <= 100) {
                $rangeKey = '50-100';
                $rangeLabel = '$50 - $100';
            } elseif ($monthlyAverage <= 200) {
                $rangeKey = '100-200';
                $rangeLabel = '$100 - $200';
            } elseif ($monthlyAverage <= 500) {
                $rangeKey = '200-500';
                $rangeLabel = '$200 - $500';
            } else {
                $rangeKey = '500-ADELANTE';
                $rangeLabel = '$500 en adelante';
            }

            $processed->push([
                'id' => $client->id,
                'name' => $client->name,
                'nombre_local' => $client->nombre_local,
                'identification' => $client->identification,
                'email' => $client->email,
                'phone' => $client->phone,
                'total_invoiced' => round($totalInvoiced, 2),
                'invoice_count' => $invoiceCount,
                'monthly_average' => $monthlyAverage,
                'range_key' => $rangeKey,
                'range_label' => $rangeLabel,
            ]);
        }

        // Aplicar Filtro de Búsqueda
        if ($request->search) {
            $s = strtolower(trim($request->search));
            $processed = $processed->filter(function ($item) use ($s) {
                return str_contains(strtolower($item['name']), $s) ||
                    str_contains(strtolower($item['nombre_local'] ?? ''), $s) ||
                    str_contains(strtolower($item['identification'] ?? ''), $s);
            });
        }

        // Conteo y totales por rangos (para tarjetas de resumen)
        $rangesSummary = [
            '0-50' => ['count' => $processed->where('range_key', '0-50')->count(), 'sum' => round($processed->where('range_key', '0-50')->sum('monthly_average'), 2)],
            '50-100' => ['count' => $processed->where('range_key', '50-100')->count(), 'sum' => round($processed->where('range_key', '50-100')->sum('monthly_average'), 2)],
            '100-200' => ['count' => $processed->where('range_key', '100-200')->count(), 'sum' => round($processed->where('range_key', '100-200')->sum('monthly_average'), 2)],
            '200-500' => ['count' => $processed->where('range_key', '200-500')->count(), 'sum' => round($processed->where('range_key', '200-500')->sum('monthly_average'), 2)],
            '500-ADELANTE' => ['count' => $processed->where('range_key', '500-ADELANTE')->count(), 'sum' => round($processed->where('range_key', '500-ADELANTE')->sum('monthly_average'), 2)],
        ];

        // Aplicar Filtro por Rango específico si el usuario lo seleccionó
        if ($request->range && isset($rangesSummary[$request->range])) {
            $processed = $processed->where('range_key', $request->range);
        }

        $processed = $processed->sortByDesc('monthly_average')->values();

        $summary = [
            'active_clients_count' => $processed->count(),
            'total_invoiced_all' => round($processed->sum('total_invoiced'), 2),
            'overall_monthly_average' => round($processed->sum('monthly_average'), 2),
            'months_count' => (int)$monthsCount,
            'ranges' => $rangesSummary,
        ];

        return [
            'clients' => $processed,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Reporte 2: Cliente y sus sucursales con geolocalización obtenida de la última venta o visita,
     * utilizando la API de Google para determinar Sector y Parroquia.
     */
    public function branchesGeolocation(Request $request, GoogleGeocodingService $geocoding)
    {
        $isGenerated = $request->boolean('generate') || $request->filled('generate') || $request->filled('search') || $request->filled('has_geo');

        if (!$isGenerated) {
            $paginated = new LengthAwarePaginator(
                [],
                0,
                30,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return Inertia::render('Admin/Reports/BranchesGeolocation', [
                'branches' => $paginated,
                'summary' => [
                    'total_branches' => 0,
                    'geolocated_count' => 0,
                    'missing_geo_count' => 0,
                    'sectors_count' => 0,
                ],
                'filters' => [
                    'has_geo' => $request->has_geo ?? '',
                    'search' => $request->search ?? '',
                    'generate' => '',
                ],
                'is_generated' => false,
            ]);
        }

        $data = $this->getProcessedBranchesGeolocation($request, $geocoding);
        $branchesCollection = $data['branches'];

        // Paginación
        $page = (int)($request->page ?? 1);
        $perPage = 30;
        $paginatedItems = $branchesCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $paginatedItems,
            $branchesCollection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Admin/Reports/BranchesGeolocation', [
            'branches' => $paginated,
            'summary' => $data['summary'],
            'filters' => [
                'has_geo' => $request->has_geo ?? '',
                'search' => $request->search ?? '',
                'generate' => '1',
            ],
            'is_generated' => true,
        ]);
    }

    public function exportBranchesGeolocation(Request $request, GoogleGeocodingService $geocoding)
    {
        return $this->exportBranchesGeolocationExcel($request, $geocoding);
    }

    public function exportBranchesGeolocationExcel(Request $request, GoogleGeocodingService $geocoding)
    {
        $data = $this->getProcessedBranchesGeolocation($request, $geocoding);
        $branches = $data['branches'];
        $summary = $data['summary'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Geolocalización Sucursales');
        $sheet->setShowGridLines(true);

        // 1. Título Principal
        $sheet->mergeCells('A1:L2');
        $sheet->setCellValue('A1', 'GRUPO DADOS - REPORTE DE GEOLOCALIZACIÓN Y SUCURSALES');
        $sheet->getStyle('A1:L2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Fecha de Emisión: ' . date('d/m/Y H:i:s'));
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

        // 2. Tarjetas KPI
        $cardHeaderStyle = [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        $cardValueStyle = [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '0284C7']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        $sheet->mergeCells('A5:C5');
        $sheet->setCellValue('A5', 'TOTAL SUCURSALES');
        $sheet->getStyle('A5:C5')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('A6:C6');
        $sheet->setCellValue('A6', $summary['total_branches']);
        $sheet->getStyle('A6:C6')->applyFromArray($cardValueStyle);

        $sheet->mergeCells('D5:F5');
        $sheet->setCellValue('D5', 'GEOLOCALIZADAS');
        $sheet->getStyle('D5:F5')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('D6:F6');
        $sheet->setCellValue('D6', $summary['geolocated_count']);
        $sheet->getStyle('D6:F6')->applyFromArray($cardValueStyle);

        $sheet->mergeCells('G5:I5');
        $sheet->setCellValue('G5', 'SIN COORDENADAS');
        $sheet->getStyle('G5:I5')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('G6:I6');
        $sheet->setCellValue('G6', $summary['missing_geo_count']);
        $sheet->getStyle('G6:I6')->applyFromArray($cardValueStyle);

        $sheet->mergeCells('J5:L5');
        $sheet->setCellValue('J5', 'SECTORES ÚNICOS');
        $sheet->getStyle('J5:L5')->applyFromArray($cardHeaderStyle);
        $sheet->mergeCells('J6:L6');
        $sheet->setCellValue('J6', $summary['sectors_count']);
        $sheet->getStyle('J6:L6')->applyFromArray($cardValueStyle);

        // 3. Encabezados de Tabla
        $row = 8;
        $headers = [
            'A' => 'ID',
            'B' => 'Cliente',
            'C' => 'RUC / Cédula',
            'D' => 'Código Sucursal',
            'E' => 'Sucursal',
            'F' => 'Ruta',
            'G' => 'Origen Ubicación',
            'H' => 'Última Interacción',
            'I' => 'Coordenadas (Lat, Lng)',
            'J' => 'Sector / Barrio (Google)',
            'K' => 'Parroquia / Cantón',
            'L' => 'Dirección Registrada',
        ];

        foreach ($headers as $col => $text) {
            $sheet->setCellValue($col . $row, $text);
        }

        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(25);

        // 4. Filas de Datos
        $row++;
        foreach ($branches as $b) {
            $coordsText = $b['has_geo'] ? "{$b['latitude']}, {$b['longitude']}" : 'Sin coordenadas';

            $sheet->setCellValue('A' . $row, '#' . $b['id']);
            $sheet->setCellValue('B' . $row, $b['client_name']);
            $sheet->setCellValue('C' . $row, $b['client_identification'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $b['code'] ?? 'N/A');
            $sheet->setCellValue('E' . $row, $b['branch_name']);
            $sheet->setCellValue('F' . $row, $b['route'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, $b['geo_source']);
            $sheet->setCellValue('H' . $row, $b['last_interaction_date'] ?? 'N/A');
            $sheet->setCellValue('I' . $row, $coordsText);
            $sheet->setCellValue('J' . $row, $b['sector']);
            $sheet->setCellValue('K' . $row, $b['parroquia']);
            $sheet->setCellValue('L' . $row, $b['address'] ?? 'N/A');

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}:I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $zebra = ($row % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($zebra);

            // Resaltar Origen Ubicación
            if ($b['has_geo']) {
                $sheet->getStyle("G{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
                $sheet->getStyle("G{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('166534'))->setBold(true);
            }

            // Resaltar Sector / Barrio si fue encontrado
            if ($b['sector'] && !in_array($b['sector'], ['Sin especificar', 'Sin coordenadas', 'No encontrado'])) {
                $sheet->getStyle("J{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('CFFAFE');
                $sheet->getStyle("J{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('155E75'))->setBold(true);
            }

            // Resaltar Parroquia si fue encontrada
            if ($b['parroquia'] && !in_array($b['parroquia'], ['Sin especificar', 'Sin coordenadas', 'No encontrado'])) {
                $sheet->getStyle("K{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F3E8FF');
                $sheet->getStyle("K{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('6B21A8'))->setBold(true);
            }

            $sheet->getStyle("A{$row}:L{$row}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

            $row++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Reporte_Geolocalizacion_Sucursales_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function resolveFrequentLocationForBranch(Branch $branch): array
    {
        $client = $branch->client;

        // 1. Obtener la ubicación de visitas más frecuente para esta sucursal (o cliente)
        $visitCluster = Visit::select(
                DB::raw('ROUND(latitude, 4) as lat_group'),
                DB::raw('ROUND(longitude, 4) as lng_group'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('MAX(id) as last_visit_id'),
                DB::raw('MAX(created_at) as last_time')
            )
            ->where('branch_id', $branch->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->groupBy(DB::raw('ROUND(latitude, 4), ROUND(longitude, 4)'))
            ->orderByDesc('cnt')
            ->orderByDesc('last_time')
            ->first();

        if (!$visitCluster && $client) {
            $visitCluster = Visit::select(
                    DB::raw('ROUND(latitude, 4) as lat_group'),
                    DB::raw('ROUND(longitude, 4) as lng_group'),
                    DB::raw('COUNT(*) as cnt'),
                    DB::raw('MAX(id) as last_visit_id'),
                    DB::raw('MAX(created_at) as last_time')
                )
                ->where('client_id', $client->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->groupBy(DB::raw('ROUND(latitude, 4), ROUND(longitude, 4)'))
                ->orderByDesc('cnt')
                ->orderByDesc('last_time')
                ->first();
        }

        // 2. Obtener la ubicación de ventas más frecuente para esta sucursal (o cliente)
        $orderCluster = Order::select(
                DB::raw('ROUND(latitude, 4) as lat_group'),
                DB::raw('ROUND(longitude, 4) as lng_group'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('MAX(id) as last_order_id'),
                DB::raw('MAX(created_at) as last_time')
            )
            ->where('branch_id', $branch->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->groupBy(DB::raw('ROUND(latitude, 4), ROUND(longitude, 4)'))
            ->orderByDesc('cnt')
            ->orderByDesc('last_time')
            ->first();

        if (!$orderCluster && $client) {
            $orderCluster = Order::select(
                    DB::raw('ROUND(latitude, 4) as lat_group'),
                    DB::raw('ROUND(longitude, 4) as lng_group'),
                    DB::raw('COUNT(*) as cnt'),
                    DB::raw('MAX(id) as last_order_id'),
                    DB::raw('MAX(created_at) as last_time')
                )
                ->where('client_id', $client->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->groupBy(DB::raw('ROUND(latitude, 4), ROUND(longitude, 4)'))
                ->orderByDesc('cnt')
                ->orderByDesc('last_time')
                ->first();
        }

        $latitude = null;
        $longitude = null;
        $geoSource = 'Sin coordenadas';
        $interactionDate = null;

        $useVisit = false;
        $useOrder = false;

        // Regla:
        // Prioridad 1: Visita más frecuente (si cnt > 1).
        // Prioridad 2: Venta más frecuente (si cnt > 1).
        // Prioridad 3: Si solo se tiene 1 interacción (cnt == 1 en ambas o una), usar la interacción más reciente en el tiempo (última visita vs última venta).
        if ($visitCluster && $visitCluster->cnt > 1) {
            $useVisit = true;
        } elseif ($orderCluster && $orderCluster->cnt > 1) {
            $useOrder = true;
        } elseif ($visitCluster && $orderCluster) {
            if (Carbon::parse($visitCluster->last_time)->gte(Carbon::parse($orderCluster->last_time))) {
                $useVisit = true;
            } else {
                $useOrder = true;
            }
        } elseif ($visitCluster) {
            $useVisit = true;
        } elseif ($orderCluster) {
            $useOrder = true;
        }

        if ($useVisit && $visitCluster) {
            $sampleVisit = Visit::find($visitCluster->last_visit_id);
            if ($sampleVisit) {
                $latitude = (float)$sampleVisit->latitude;
                $longitude = (float)$sampleVisit->longitude;
                $cnt = $visitCluster->cnt;
                $geoSource = $cnt > 1 ? "Visita Frecuente ({$cnt} visitas)" : "Última Visita (#{$sampleVisit->id})";
                $interactionDate = Carbon::parse($sampleVisit->created_at)->format('d/m/Y H:i');
            }
        } elseif ($useOrder && $orderCluster) {
            $sampleOrder = Order::find($orderCluster->last_order_id);
            if ($sampleOrder) {
                $latitude = (float)$sampleOrder->latitude;
                $longitude = (float)$sampleOrder->longitude;
                $cnt = $orderCluster->cnt;
                $geoSource = $cnt > 1 ? "Venta Frecuente ({$cnt} pedidos)" : "Última Venta (#{$sampleOrder->id})";
                $interactionDate = Carbon::parse($sampleOrder->created_at)->format('d/m/Y H:i');
            }
        } elseif (!empty($branch->latitude) && !empty($branch->longitude)) {
            $latitude = (float)$branch->latitude;
            $longitude = (float)$branch->longitude;
            $geoSource = "Ubicación de Sucursal";
            $interactionDate = $branch->updated_at ? Carbon::parse($branch->updated_at)->format('d/m/Y') : 'N/A';
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geo_source' => $geoSource,
            'interaction_date' => $interactionDate,
        ];
    }

    protected function getProcessedBranchesGeolocation(Request $request, GoogleGeocodingService $geocoding): array
    {
        $branches = Branch::with(['client'])->get();

        $processed = collect();

        foreach ($branches as $branch) {
            $client = $branch->client;
            if (!$client) continue;

            $geoInfo = $this->resolveFrequentLocationForBranch($branch);
            $latitude = $geoInfo['latitude'];
            $longitude = $geoInfo['longitude'];
            $geoSource = $geoInfo['geo_source'];
            $interactionDate = $geoInfo['interaction_date'];

            $sector = 'Sin especificar';
            $parroquia = 'Sin especificar';
            $googleAddress = 'N/A';
            $hasGeo = false;

            if (!empty($latitude) && !empty($longitude)) {
                $hasGeo = true;

                // 1. Nivel 1: Verificar si ya existe información almacenada en la Base de Datos
                $dbSectorValid = !empty($branch->sector) && !in_array($branch->sector, ['Sin especificar', 'No encontrado', 'Activar Geocoding API en Google Cloud']);
                
                if ($dbSectorValid) {
                    $sector = $branch->sector;
                    $parroquia = $branch->parroquia ?? 'Sin especificar';
                    $googleAddress = $branch->google_address ?? 'N/A';
                } else {
                    // 2. Nivel 2 y 3: Consultar servicio de Caché o API de Google Maps
                    $geoRes = $geocoding->getSectorAndParroquia($latitude, $longitude);
                    $sector = $geoRes['sector'] ?? 'Sin especificar';
                    $parroquia = $geoRes['parroquia'] ?? 'Sin especificar';
                    $googleAddress = $geoRes['formatted_address'] ?? 'N/A';

                    // Si la respuesta fue exitosa (OK), guardar automáticamente en la Base de Datos
                    if (($geoRes['status'] ?? '') === 'OK') {
                        try {
                            $branch->update([
                                'sector' => $sector,
                                'parroquia' => $parroquia,
                                'google_address' => $googleAddress,
                                'geocoded_at' => now(),
                            ]);
                        } catch (\Throwable $e) {
                            // Ignorar silentes de BD si aplica
                        }
                    }
                }
            }

            $processed->push([
                'id' => $branch->id,
                'code' => $branch->code,
                'branch_name' => $branch->name,
                'route' => $branch->route,
                'address' => $branch->address,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_identification' => $client->identification,
                'has_geo' => $hasGeo,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geo_source' => $geoSource,
                'last_interaction_date' => $interactionDate,
                'sector' => $sector,
                'parroquia' => $parroquia,
                'google_address' => $googleAddress,
                'maps_url' => $hasGeo ? "https://www.google.com/maps/search/?api=1&query={$latitude},{$longitude}" : null,
            ]);
        }

        // Aplicar Filtros
        if ($request->search) {
            $s = strtolower(trim($request->search));
            $processed = $processed->filter(function ($item) use ($s) {
                return str_contains(strtolower($item['client_name']), $s) ||
                    str_contains(strtolower($item['branch_name']), $s) ||
                    str_contains(strtolower($item['route'] ?? ''), $s) ||
                    str_contains(strtolower($item['sector']), $s) ||
                    str_contains(strtolower($item['parroquia']), $s) ||
                    str_contains(strtolower($item['address'] ?? ''), $s);
            });
        }

        if ($request->has_geo === 'yes') {
            $processed = $processed->where('has_geo', true);
        } elseif ($request->has_geo === 'no') {
            $processed = $processed->where('has_geo', false);
        }

        $processed = $processed->sortBy('client_name')->values();

        $summary = [
            'total_branches' => $processed->count(),
            'geolocated_count' => $processed->where('has_geo', true)->count(),
            'missing_geo_count' => $processed->where('has_geo', false)->count(),
            'sectors_count' => $processed->pluck('sector')->unique()->filter(fn($v) => $v !== 'Sin especificar' && $v !== 'Sin coordenadas')->count(),
        ];

        return [
            'branches' => $processed,
            'summary' => $summary,
        ];
    }

    public function presaleDirectSale(Request $request, ContificoService $contifico)
    {
        $data = $this->getPresaleDirectSaleData($request, $contifico);

        return Inertia::render('Admin/Reports/PresaleDirectSale', [
            'reportData' => $data['reportData'],
            'products' => $data['products'],
            'totals' => $data['totals'],
            'filters' => [
                'mode' => $request->mode ?? ($request->date_from ? 'range' : 'date'),
                'date' => $data['date'],
                'date_from' => $data['dateFromStr'],
                'date_to' => $data['dateToStr'],
            ]
        ]);
    }

    public function exportPresaleDirectSale(Request $request, ContificoService $contifico)
    {
        $data = $this->getPresaleDirectSaleData($request, $contifico);
        $reportData = $data['reportData'];
        $products = $data['products'];
        $totals = $data['totals'];

        $dateLabel = $data['dateFromStr'] === $data['dateToStr'] 
            ? "FECHA: " . Carbon::parse($data['dateFromStr'])->format('d/m/Y')
            : "RANGO: " . Carbon::parse($data['dateFromStr'])->format('d/m/Y') . " al " . Carbon::parse($data['dateToStr'])->format('d/m/Y');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen Preventa');
        $sheet->setShowGridLines(true);

        $productCount = count($products);
        $lastColIdx = 9 + $productCount + 1; // 9 base cols + product cols + 1 total items col
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIdx);

        // 1. Title Banner Row (Row 1)
        $sheet->setCellValue('A1', $dateLabel);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $titleEndCol = $lastColLetter;
        $sheet->mergeCells("B1:{$titleEndCol}1");
        $sheet->setCellValue('B1', 'RESUMEN DE PREVENTA Y VENTA DIRECTA');
        $sheet->getStyle("B1:{$titleEndCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // 2. Table Headers (Row 2 & Row 3)
        $headerBg = 'E7E6E6';
        $yellowBg = 'FFFF00';

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $headerBg]],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];

        $yellowHeaderStyle = [
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $yellowBg]],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];

        // Base Merged Headers
        $sheet->mergeCells('A2:A3');
        $sheet->setCellValue('A2', 'VENDEDOR');
        $sheet->getStyle('A2:A3')->applyFromArray($headerStyle);

        $sheet->mergeCells('B2:B3');
        $sheet->setCellValue('B2', 'VISITAS');
        $sheet->getStyle('B2:B3')->applyFromArray($headerStyle);

        $sheet->mergeCells('C2:C3');
        $sheet->setCellValue('C2', 'TOTAL PEDIDOS');
        $sheet->getStyle('C2:C3')->applyFromArray($yellowHeaderStyle);

        $sheet->mergeCells('D2:D3');
        $sheet->setCellValue('D2', "VENTAS\nFACTURA");
        $sheet->getStyle('D2:D3')->applyFromArray($headerStyle);

        $sheet->mergeCells('E2:E3');
        $sheet->setCellValue('E2', "VENTAS\nPREFACTUR");
        $sheet->getStyle('E2:E3')->applyFromArray($headerStyle);

        // Ventas Totales Header
        $sheet->mergeCells('F2:H2');
        $sheet->setCellValue('F2', 'VENTAS TOTALES');
        $sheet->getStyle('F2:H2')->applyFromArray($headerStyle);

        $sheet->setCellValue('F3', 'CONTADO');
        $sheet->getStyle('F3')->applyFromArray($headerStyle);

        $sheet->setCellValue('G3', 'CREDITO');
        $sheet->getStyle('G3')->applyFromArray($headerStyle);

        $sheet->setCellValue('H3', 'TRANSFER.');
        $sheet->getStyle('H3')->applyFromArray($headerStyle);

        $sheet->mergeCells('I2:I3');
        $sheet->setCellValue('I2', 'TOTAL ($)');
        $sheet->getStyle('I2:I3')->applyFromArray($yellowHeaderStyle);

        // Items Vendidos Header
        if ($productCount > 0) {
            $firstProdColLetter = 'J';
            $lastProdColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(9 + $productCount);
            
            $sheet->mergeCells("{$firstProdColLetter}2:{$lastProdColLetter}2");
            $sheet->setCellValue("{$firstProdColLetter}2", 'ITEMS VENDIDOS');
            $sheet->getStyle("{$firstProdColLetter}2:{$lastProdColLetter}2")->applyFromArray($headerStyle);

            foreach ($products as $idx => $prod) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(10 + $idx);
                $pName = mb_strtoupper($prod->name);
                $pName = str_replace('FUNDAS DE HIELO LISTO', 'FD. LISTO', $pName);
                $pName = str_replace('FUNDAS DE HIELO', 'FD.', $pName);
                $pName = str_replace('FUNDA DE HIELO', 'FD.', $pName);

                $sheet->setCellValue("{$cLetter}3", $pName);
                $sheet->getStyle("{$cLetter}3")->applyFromArray($headerStyle);
            }
        }

        // Total Items Header
        $sheet->mergeCells("{$lastColLetter}2:{$lastColLetter}3");
        $sheet->setCellValue("{$lastColLetter}2", 'TOTAL ITEMS');
        $sheet->getStyle("{$lastColLetter}2:{$lastColLetter}3")->applyFromArray($yellowHeaderStyle);

        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(25);

        // 3. Data Rows
        $rowIdx = 4;
        $currencyFormat = '"$"#,##0.00;("$"#,##0.00);"-"';
        $numberFormat = '#,##0';

        foreach ($reportData as $row) {
            $sheet->setCellValue("A{$rowIdx}", $row['seller_name']);
            $sheet->setCellValue("B{$rowIdx}", $row['visits']);
            $sheet->setCellValue("C{$rowIdx}", $row['orders_count']);
            $sheet->setCellValue("D{$rowIdx}", (float)$row['invoiced_amount']);
            $sheet->setCellValue("E{$rowIdx}", (float)$row['preinvoiced_amount']);
            $sheet->setCellValue("F{$rowIdx}", (float)$row['cash_amount']);
            $sheet->setCellValue("G{$rowIdx}", (float)$row['credit_amount']);
            $sheet->setCellValue("H{$rowIdx}", (float)$row['transfer_amount']);
            $sheet->setCellValue("I{$rowIdx}", (float)$row['total_amount']);

            // Product Item Quantities
            foreach ($products as $idx => $prod) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(10 + $idx);
                $qty = (int)($row['product_quantities'][$prod->id] ?? 0);
                $sheet->setCellValue("{$cLetter}{$rowIdx}", $qty);
            }

            $sheet->setCellValue("{$lastColLetter}{$rowIdx}", (int)$row['total_items']);

            // Styling Row
            $sheet->getStyle("A{$rowIdx}:{$lastColLetter}{$rowIdx}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                'font' => ['size' => 9],
            ]);

            $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Align Right for amounts & quantities
            $sheet->getStyle("D{$rowIdx}:{$lastColLetter}{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            // Number Formats
            $sheet->getStyle("B{$rowIdx}:C{$rowIdx}")->getNumberFormat()->setFormatCode($numberFormat);
            $sheet->getStyle("D{$rowIdx}:I{$rowIdx}")->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle("J{$rowIdx}:{$lastColLetter}{$rowIdx}")->getNumberFormat()->setFormatCode($numberFormat);

            // Highlight Total Pedidos, Total ($), Total Items
            $sheet->getStyle("C{$rowIdx}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFE0');
            $sheet->getStyle("C{$rowIdx}")->getFont()->setBold(true);

            $sheet->getStyle("I{$rowIdx}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF99');
            $sheet->getStyle("I{$rowIdx}")->getFont()->setBold(true);

            $sheet->getStyle("{$lastColLetter}{$rowIdx}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF99');
            $sheet->getStyle("{$lastColLetter}{$rowIdx}")->getFont()->setBold(true);

            $rowIdx++;
        }

        // 4. Total General Row
        $sheet->setCellValue("A{$rowIdx}", 'Total general');
        $sheet->setCellValue("B{$rowIdx}", $totals['visits']);
        $sheet->setCellValue("C{$rowIdx}", $totals['orders_count']);
        $sheet->setCellValue("D{$rowIdx}", (float)$totals['invoiced_amount']);
        $sheet->setCellValue("E{$rowIdx}", (float)$totals['preinvoiced_amount']);
        $sheet->setCellValue("F{$rowIdx}", (float)$totals['cash_amount']);
        $sheet->setCellValue("G{$rowIdx}", (float)$totals['credit_amount']);
        $sheet->setCellValue("H{$rowIdx}", (float)$totals['transfer_amount']);
        $sheet->setCellValue("I{$rowIdx}", (float)$totals['total_amount']);

        foreach ($products as $idx => $prod) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(10 + $idx);
            $sheet->setCellValue("{$cLetter}{$rowIdx}", (int)($totals['product_quantities'][$prod->id] ?? 0));
        }

        $sheet->setCellValue("{$lastColLetter}{$rowIdx}", (int)$totals['total_items']);

        // Styling Total Row
        $totalRowRange = "A{$rowIdx}:{$lastColLetter}{$rowIdx}";
        $sheet->getStyle($totalRowRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                'bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE, 'color' => ['rgb' => '000000']],
                'left' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
                'right' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        $sheet->getStyle("A{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$rowIdx}:{$lastColLetter}{$rowIdx}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("B{$rowIdx}:C{$rowIdx}")->getNumberFormat()->setFormatCode($numberFormat);
        $sheet->getStyle("D{$rowIdx}:I{$rowIdx}")->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle("J{$rowIdx}:{$lastColLetter}{$rowIdx}")->getNumberFormat()->setFormatCode($numberFormat);

        // Extra Highlight for Key Total General Cells
        $sheet->getStyle("C{$rowIdx}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        $sheet->getStyle("I{$rowIdx}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        $sheet->getStyle("{$lastColLetter}{$rowIdx}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');

        // Auto-fit Column Widths
        for ($col = 1; $col <= $lastColIdx; $col++) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($cLetter)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Resumen_Preventa_VentaDirecta_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function getPresaleDirectSaleData(Request $request, ?ContificoService $contifico = null)
    {
        $todayStr = Carbon::now()->format('Y-m-d');
        if ($request->mode === 'range' || ($request->date_from && $request->date_to)) {
            $dateFromStr = $request->date_from ?? $todayStr;
            $dateToStr = $request->date_to ?? $todayStr;
        } else {
            $dateStr = $request->date ?? $request->date_from ?? $todayStr;
            $dateFromStr = $dateStr;
            $dateToStr = $dateStr;
        }

        $from = Carbon::parse($dateFromStr)->startOfDay();
        $to = Carbon::parse($dateToStr)->endOfDay();

        // Fetch active products
        $products = \App\Models\Product::where('status', 'A')
            ->where('is_visible', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        $productContificoMap = \App\Models\Product::whereNotNull('contifico_id')
            ->pluck('id', 'contifico_id')
            ->toArray();

        // Sellers: Users with role seller OR who have created orders/visits
        $sellers = User::where(function($q) {
                $q->where('role', 'seller')
                  ->orWhereHas('orders')
                  ->orWhereHas('visits');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $sellerIds = $sellers->pluck('id')->toArray();

        // Prefetch visits grouped by user_id
        $visitsGrouped = Visit::whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Fetch local DB orders for the range (exclude cancelled)
        $orders = Order::with('items')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->get();

        // Fetch cancelled orders for the range to keep track of their contifico_id and order_id
        $cancelledOrderIds = Order::whereBetween('created_at', [$from, $to])
            ->where('status', 'cancelled')
            ->pluck('id')
            ->toArray();

        $cancelledContificoIds = Order::whereBetween('created_at', [$from, $to])
            ->where('status', 'cancelled')
            ->pluck('contifico_id')
            ->filter()
            ->toArray();

        // Fetch Contifico direct invoices if ContificoService is available
        $contificoInvoices = [];
        if ($contifico) {
            try {
                $forceRefresh = $request->boolean('refresh');
                $rawInvoices = $contifico->fetchInvoices(
                    $dateFromStr,
                    $dateToStr,
                    $forceRefresh
                );
                if (!empty($rawInvoices) && is_array($rawInvoices)) {
                    foreach ($rawInvoices as $inv) {
                        // Exclude cancelled/voided invoices in Contifico
                        $isAnulado = !empty($inv['anulado']) || in_array($inv['estado'] ?? '', ['AN', 'A', 'E']);
                        $docTotal = (float)($inv['total'] ?? 0);
                        // Excluir si está anulada o si tiene total negativo
                        if ($isAnulado || $docTotal < 0) {
                            continue;
                        }
                        // Si el total es 0, solo incluirla si tiene ítems/detalles entregados (ej. donaciones o promociones al 100%)
                        if ($docTotal == 0 && empty($inv['detalles'])) {
                            continue;
                        }
                        $contificoInvoices[] = $inv;
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Could not fetch Contifico direct invoices for report: " . $e->getMessage());
            }
        }

        // Create index maps for Contifico invoices
        $contificoInvById = [];
        $contificoInvByPedidoId = [];
        foreach ($contificoInvoices as $inv) {
            if (!empty($inv['id'])) {
                $contificoInvById[$inv['id']] = $inv;
            }
            $invDesc = $inv['descripcion'] ?? '';
            if (preg_match('/Pedido\s*#(\d+)/i', $invDesc, $matches)) {
                $pId = (int)$matches[1];
                $contificoInvByPedidoId[$pId] = $inv;
            }
        }

        $processedContificoInvIds = [];

        // Build seller structure
        $sellerDataMap = [];
        foreach ($sellers as $seller) {
            $sellerDataMap[$seller->id] = [
                'user_id' => $seller->id,
                'seller_name' => $seller->name,
                'visits' => (int)($visitsGrouped[$seller->id] ?? 0),
                'orders_count' => 0,
                'invoiced_amount' => 0.0,
                'preinvoiced_amount' => 0.0,
                'cash_amount' => 0.0,
                'credit_amount' => 0.0,
                'transfer_amount' => 0.0,
                'total_amount' => 0.0,
                'product_quantities' => array_fill_keys($products->pluck('id')->toArray(), 0),
                'total_items' => 0,
            ];
        }

        $contificoRow = [
            'user_id' => 'contifico',
            'seller_name' => 'Ventas en Contifico',
            'visits' => 0,
            'orders_count' => 0,
            'invoiced_amount' => 0.0,
            'preinvoiced_amount' => 0.0,
            'cash_amount' => 0.0,
            'credit_amount' => 0.0,
            'transfer_amount' => 0.0,
            'total_amount' => 0.0,
            'product_quantities' => array_fill_keys($products->pluck('id')->toArray(), 0),
            'total_items' => 0,
        ];

        if (!empty($contificoInvoices)) {
            // =========================================================================
            // CONTIFICO COMO FUENTE DE LA VERDAD (CUADRE 100% EN DÓLARES Y EN ÍTEMS)
            // =========================================================================
            $ordersByContificoId = [];
            $ordersById = [];
            foreach ($orders as $ord) {
                if (!empty($ord->contifico_id)) {
                    $ordersByContificoId[$ord->contifico_id] = $ord;
                }
                $ordersById[$ord->id] = $ord;
            }

            $invoicedLocalOrderIds = [];

            // Procesar cada factura válida de Contifico
            foreach ($contificoInvoices as $inv) {
                $invId = $inv['id'] ?? '';
                $desc = $inv['descripcion'] ?? '';
                $vend = is_array($inv['vendedor'] ?? null) ? json_encode($inv['vendedor']) : (string)($inv['vendedor'] ?? '');
                $docTotal = (float)($inv['total'] ?? 0);

                $sellerId = null;
                $matchedOrder = null;

                // Prioridad 1: Match por contifico_id del pedido en la app
                if (!empty($invId) && isset($ordersByContificoId[$invId])) {
                    $matchedOrder = $ordersByContificoId[$invId];
                    if ($matchedOrder->user_id && isset($sellerDataMap[$matchedOrder->user_id])) {
                        $sellerId = $matchedOrder->user_id;
                    }
                }

                // Prioridad 2: Match por 'Pedido #XXX' en la descripción
                if (!$sellerId && preg_match('/Pedido\s*#(\d+)/i', $desc, $matches)) {
                    $pId = (int)$matches[1];
                    if (isset($ordersById[$pId])) {
                        $matchedOrder = $ordersById[$pId];
                        if ($matchedOrder->user_id && isset($sellerDataMap[$matchedOrder->user_id])) {
                            $sellerId = $matchedOrder->user_id;
                        }
                    }
                }

                // Prioridad 3: Match por 'Atendido por: Nombre Vendedor' en la descripción
                if (!$sellerId && preg_match('/Atendido por:\s*([^-\n]+)/i', $desc, $matches)) {
                    $atendido = trim($matches[1]);
                    foreach ($sellers as $s) {
                        if (mb_stripos($atendido, $s->name) !== false || mb_stripos($s->name, $atendido) !== false) {
                            $sellerId = $s->id;
                            break;
                        }
                    }
                }

                // Prioridad 4: Match por campo 'vendedor' de Contifico
                if (!$sellerId && !empty($vend)) {
                    foreach ($sellers as $s) {
                        $parts = explode(' ', strtolower($s->name));
                        $matchParts = 0;
                        foreach ($parts as $p) {
                            if (strlen($p) > 2 && str_contains(strtolower($vend), $p)) {
                                $matchParts++;
                            }
                        }
                        if ($matchParts >= 2 || ($matchParts >= 1 && count($parts) == 1)) {
                            $sellerId = $s->id;
                            break;
                        }
                    }
                }

                // Prioridad 5: Match por 'Vendedor: Nombre' en descripción
                if (!$sellerId && preg_match('/Vendedor:\s*([^-\n]+)/i', $desc, $matches)) {
                    $vendedorDesc = trim($matches[1]);
                    foreach ($sellers as $s) {
                        if (mb_stripos($vendedorDesc, $s->name) !== false || mb_stripos($s->name, $vendedorDesc) !== false) {
                            $sellerId = $s->id;
                            break;
                        }
                    }
                }

                // Prioridad 6: Nombre del vendedor mencionado en cualquier parte de la descripción
                if (!$sellerId) {
                    foreach ($sellers as $s) {
                        if (mb_stripos($desc, $s->name) !== false) {
                            $sellerId = $s->id;
                            break;
                        }
                    }
                }

                if ($matchedOrder) {
                    $invoicedLocalOrderIds[] = $matchedOrder->id;
                }

                // Asignar al vendedor correspondiente o a "Ventas en Contifico"
                if ($sellerId && isset($sellerDataMap[$sellerId])) {
                    $targetRow = &$sellerDataMap[$sellerId];
                } else {
                    $targetRow = &$contificoRow;
                }

                $targetRow['orders_count']++;
                $targetRow['invoiced_amount'] += $docTotal;
                $targetRow['total_amount'] += $docTotal;

                // Forma de Pago (Crédito, Transferencia, Contado)
                $emision = $inv['fecha_emision'] ?? null;
                $vencimiento = $inv['fecha_vencimiento'] ?? null;
                $cobros = $inv['cobros'] ?? [];
                $formaCobro = !empty($cobros) ? mb_strtoupper($cobros[0]['forma_cobro'] ?? $cobros[0]['forma_pago'] ?? '') : '';
                $pm = mb_strtoupper($inv['forma_pago'] ?? $inv['metodo_pago'] ?? '');

                if ($matchedOrder && (($matchedOrder->credit_days ?? 0) > 0 || str_contains(strtolower($matchedOrder->payment_method ?? ''), 'cred'))) {
                    $targetRow['credit_amount'] += $docTotal;
                } elseif (($emision && $vencimiento && $emision !== $vencimiento) || ($inv['dias_vencimiento'] ?? 0) > 0 || str_contains($pm, 'CRED') || str_contains($pm, 'CRÉD') || str_contains($formaCobro, 'CRED') || str_contains($formaCobro, 'CRÉD')) {
                    $targetRow['credit_amount'] += $docTotal;
                } elseif (str_contains($pm, 'TRANSF') || str_contains($pm, 'BANCO') || str_contains($pm, 'DEPOSITO') || str_contains($pm, 'DEPÓSITO') || str_contains($formaCobro, 'TRA') || str_contains($formaCobro, 'DEP')) {
                    $targetRow['transfer_amount'] += $docTotal;
                } else {
                    $targetRow['cash_amount'] += $docTotal;
                }

                // Ítems vendidos directamente desde los detalles de la factura de Contifico
                if (!empty($inv['detalles']) && is_array($inv['detalles'])) {
                    foreach ($inv['detalles'] as $det) {
                        $cProdId = $det['producto_id'] ?? null;
                        $qty = (int)($det['cantidad'] ?? 0);
                        if ($cProdId && isset($productContificoMap[$cProdId])) {
                            $localPId = $productContificoMap[$cProdId];
                            if (isset($targetRow['product_quantities'][$localPId])) {
                                $targetRow['product_quantities'][$localPId] += $qty;
                            }
                        }
                        $targetRow['total_items'] += $qty;
                    }
                }

                unset($targetRow);
            }

            // Procesar pedidos locales que NO fueron facturados en Contifico (Pre-facturas de la app)
            foreach ($orders as $ord) {
                if (in_array($ord->id, $invoicedLocalOrderIds)) {
                    continue;
                }
                if ($ord->status === 'cancelled') {
                    continue;
                }
                if ($ord->user_id && isset($sellerDataMap[$ord->user_id])) {
                    $sellerDataMap[$ord->user_id]['orders_count']++;
                    $sellerDataMap[$ord->user_id]['preinvoiced_amount'] += (float)$ord->total;
                }
            }

            $contificoRow['total_amount'] = $contificoRow['invoiced_amount'];

        } else {
            // =========================================================================
            // FALLBACK LOCAL DB (Si Contifico estuviese desconectado o sin facturas)
            // =========================================================================
            $ordersGrouped = $orders->groupBy('user_id');
            foreach ($sellers as $seller) {
                $userOrders = $ordersGrouped->get($seller->id, collect());
                $sellerData = &$sellerDataMap[$seller->id];

                foreach ($userOrders as $ord) {
                    $ordTotal = (float)$ord->total;
                    if ($ord->is_invoiced) {
                        $sellerData['orders_count']++;
                        $sellerData['invoiced_amount'] += $ordTotal;

                        $pm = mb_strtolower($ord->payment_method ?? '');
                        if (str_contains($pm, 'cred') || ($ord->credit_days ?? 0) > 0) {
                            $sellerData['credit_amount'] += $ordTotal;
                        } elseif (str_contains($pm, 'transf') || str_contains($pm, 'banco') || str_contains($pm, 'deposito')) {
                            $sellerData['transfer_amount'] += $ordTotal;
                        } else {
                            $sellerData['cash_amount'] += $ordTotal;
                        }

                        foreach ($ord->items as $item) {
                            $pId = $item->product_id;
                            $qty = (int)$item->quantity;
                            if (isset($sellerData['product_quantities'][$pId])) {
                                $sellerData['product_quantities'][$pId] += $qty;
                            }
                            $sellerData['total_items'] += $qty;
                        }
                    } else {
                        $sellerData['orders_count']++;
                        $sellerData['preinvoiced_amount'] += $ordTotal;
                    }
                }
                $sellerData['total_amount'] = $sellerData['invoiced_amount'];
                unset($sellerData);
            }
        }

        $reportData = array_values($sellerDataMap);
        $reportData[] = $contificoRow;

        // Build global totals
        $totals = [
            'visits' => 0,
            'orders_count' => 0,
            'invoiced_amount' => 0.0,
            'preinvoiced_amount' => 0.0,
            'cash_amount' => 0.0,
            'credit_amount' => 0.0,
            'transfer_amount' => 0.0,
            'total_amount' => 0.0,
            'product_quantities' => array_fill_keys($products->pluck('id')->toArray(), 0),
            'total_items' => 0,
        ];

        foreach ($reportData as $row) {
            $totals['visits'] += $row['visits'];
            $totals['orders_count'] += $row['orders_count'];
            $totals['invoiced_amount'] += $row['invoiced_amount'];
            $totals['preinvoiced_amount'] += $row['preinvoiced_amount'];
            $totals['cash_amount'] += $row['cash_amount'];
            $totals['credit_amount'] += $row['credit_amount'];
            $totals['transfer_amount'] += $row['transfer_amount'];
            $totals['total_amount'] += $row['total_amount'];
            $totals['total_items'] += $row['total_items'];

            foreach ($row['product_quantities'] as $pId => $qty) {
                if (!isset($totals['product_quantities'][$pId])) {
                    $totals['product_quantities'][$pId] = 0;
                }
                $totals['product_quantities'][$pId] += $qty;
            }
        }

        // Round all amounts
        foreach ($reportData as &$row) {
            $row['invoiced_amount'] = round($row['invoiced_amount'], 2);
            $row['preinvoiced_amount'] = round($row['preinvoiced_amount'], 2);
            $row['cash_amount'] = round($row['cash_amount'], 2);
            $row['credit_amount'] = round($row['credit_amount'], 2);
            $row['transfer_amount'] = round($row['transfer_amount'], 2);
            $row['total_amount'] = round($row['total_amount'], 2);
        }

        $totals['invoiced_amount'] = round($totals['invoiced_amount'], 2);
        $totals['preinvoiced_amount'] = round($totals['preinvoiced_amount'], 2);
        $totals['cash_amount'] = round($totals['cash_amount'], 2);
        $totals['credit_amount'] = round($totals['credit_amount'], 2);
        $totals['transfer_amount'] = round($totals['transfer_amount'], 2);
        $totals['total_amount'] = round($totals['total_amount'], 2);

        return [
            'reportData' => $reportData,
            'products' => $products,
            'totals' => $totals,
            'date' => $request->date ?? $dateFromStr,
            'dateFromStr' => $dateFromStr,
            'dateToStr' => $dateToStr,
        ];
    }

    public function routeMap(Request $request, GoogleGeocodingService $geocoding)
    {
        $data = $this->getProcessedRouteMapData($request, $geocoding);

        return Inertia::render('Admin/Reports/RouteMap', [
            'branches' => $data['branches'],
            'available_routes' => $data['available_routes'],
            'selected_routes' => $data['selected_routes'],
            'product_breakdown' => $data['product_breakdown'],
            'monthly_sales' => $data['monthly_sales'],
            'summary' => $data['summary'],
            'filters' => $request->only('routes', 'from', 'to', 'search'),
            'google_maps_api_key' => config('services.google.maps_api_key') ?? env('GOOGLE_MAPS_API_KEY', ''),
        ]);
    }

    public function exportRouteMap(Request $request, GoogleGeocodingService $geocoding)
    {
        $data = $this->getProcessedRouteMapData($request, $geocoding);
        $branches = $data['branches'];
        $productBreakdown = $data['product_breakdown'];
        $selectedRoutes = $data['selected_routes'];
        $summary = $data['summary'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Hoja 1: Clientes y Coordenadas por Ruta
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sucursales en Ruta');

        $sheet->setCellValue('A1', 'REPORTE VISUAL DE RUTA Y GEOLOCALIZACIÓN');
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $routesStr = !empty($selectedRoutes) ? implode(', ', $selectedRoutes) : 'Sin Selección';
        $sheet->setCellValue('A2', "Rutas Seleccionadas: " . $routesStr);
        $sheet->setCellValue('A3', "Total Ventas en Ruta: $" . number_format($summary['total_sales'], 2));
        $sheet->setCellValue('A4', "Total Pedidos: " . $summary['total_orders']);

        $headers = ['ID', 'Cliente', 'Identificación', 'Sucursal', 'Ruta', 'Ventas ($)', 'Pedidos (#)', 'Origen / Punto Frecuente', 'Última Interacción', 'Coordenadas (Lat, Lng)', 'Sector / Barrio'];
        $colIndex = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($colIndex . '6', $h);
            $sheet->getStyle($colIndex . '6')->getFont()->setBold(true);
            $sheet->getStyle($colIndex . '6')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('F1F5F9');
            $colIndex++;
        }

        $row = 7;
        foreach ($branches as $b) {
            $sheet->setCellValue('A' . $row, '#' . $b['id']);
            $sheet->setCellValue('B' . $row, $b['client_name']);
            $sheet->setCellValue('C' . $row, $b['client_identification']);
            $sheet->setCellValue('D' . $row, $b['branch_name']);
            $sheet->setCellValue('E' . $row, $b['route'] ?? 'N/A');
            $sheet->setCellValue('F' . $row, $b['total_sales']);
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->setCellValue('G' . $row, $b['orders_count']);
            $sheet->setCellValue('H' . $row, $b['geo_source'] ?? 'Sin información');
            $sheet->setCellValue('I' . $row, $b['last_interaction_date'] ?? 'N/A');
            $sheet->setCellValue('J' . $row, $b['has_geo'] ? "{$b['latitude']}, {$b['longitude']}" : 'Sin Coordenadas');
            $sheet->setCellValue('K' . $row, $b['sector'] ?? 'Sin Especificar');
            $row++;
        }

        // Hoja 2: Desglose de Ventas por Producto
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Ventas por Producto');
        $sheet2->setCellValue('A1', 'DESGLOSE DE VENTAS POR PRODUCTO EN LA RUTA');
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers2 = ['Producto', 'Unidades Vendidas', 'Total Recaudado ($)', '% Participación'];
        $colIndex = 'A';
        foreach ($headers2 as $h) {
            $sheet2->setCellValue($colIndex . '3', $h);
            $sheet2->getStyle($colIndex . '3')->getFont()->setBold(true);
            $colIndex++;
        }

        $row2 = 4;
        foreach ($productBreakdown as $pb) {
            $sheet2->setCellValue('A' . $row2, $pb['name']);
            $sheet2->setCellValue('B' . $row2, $pb['quantity']);
            $sheet2->setCellValue('C' . $row2, $pb['total']);
            $sheet2->getStyle('C' . $row2)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet2->setCellValue('D' . $row2, $pb['share_percentage'] . '%');
            $row2++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'Reporte_Ruta_Geolocalizacion_' . date('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/public/' . $fileName);

        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer->save($tempPath);
        return response()->download($tempPath)->deleteFileAfterSend(true);
    }

    protected function getProcessedRouteMapData(Request $request, GoogleGeocodingService $geocoding): array
    {
        $availableRoutes = Branch::whereNotNull('route')
            ->where('route', '!=', '')
            ->distinct()
            ->pluck('route')
            ->sort()
            ->values()
            ->toArray();

        // Obtener rutas seleccionadas
        $selectedRoutes = [];
        if ($request->has('routes')) {
            $raw = $request->input('routes');
            if (is_array($raw)) {
                $selectedRoutes = array_filter($raw);
            } elseif (is_string($raw) && trim($raw) !== '') {
                $selectedRoutes = array_filter(explode(',', $raw));
            }
        }

        // Rango de fechas
        $dateFromStr = $request->from ?: Carbon::now()->startOfMonth()->toDateString();
        $dateToStr = $request->to ?: Carbon::now()->endOfMonth()->toDateString();

        // Si no hay ninguna ruta seleccionada, no cargar la información inicialmente
        if (empty($selectedRoutes)) {
            return [
                'branches' => [],
                'available_routes' => $availableRoutes,
                'selected_routes' => [],
                'product_breakdown' => [],
                'monthly_sales' => [],
                'summary' => [
                    'total_sales' => 0.0,
                    'total_orders' => 0,
                    'total_branches' => 0,
                    'geolocated_count' => 0,
                    'top_product' => 'N/A',
                ],
            ];
        }

        $query = Branch::with(['client']);
        if (!in_array('all', $selectedRoutes)) {
            $query->whereIn('route', $selectedRoutes);
        }

        $branches = $query->get();
        $processed = collect();
        $totalRouteSales = 0.0;
        $totalRouteOrders = 0;
        $productSalesMap = [];

        foreach ($branches as $branch) {
            $client = $branch->client;
            if (!$client) continue;

            $geoInfo = $this->resolveFrequentLocationForBranch($branch);
            $latitude = $geoInfo['latitude'];
            $longitude = $geoInfo['longitude'];
            $geoSource = $geoInfo['geo_source'];
            $interactionDate = $geoInfo['interaction_date'];

            // Ventas y pedidos del cliente/sucursal en el periodo seleccionado
            $clientOrdersQuery = Order::with('items.product')
                ->where('status', '!=', 'cancelled')
                ->where(function($q) use ($branch, $client) {
                    $q->where('branch_id', $branch->id)
                      ->orWhere('client_id', $client->id);
                })
                ->whereDate('created_at', '>=', $dateFromStr)
                ->whereDate('created_at', '<=', $dateToStr);

            $clientOrders = $clientOrdersQuery->get();
            $branchSalesTotal = $clientOrders->sum('total');
            $branchOrdersCount = $clientOrders->count();

            $totalRouteSales += $branchSalesTotal;
            $totalRouteOrders += $branchOrdersCount;

            // Agrupar desglose por producto para la ruta
            foreach ($clientOrders as $ord) {
                foreach ($ord->items as $item) {
                    $pId = $item->product_id;
                    $pName = $item->product?->name ?? 'Producto';
                    $qty = (int)$item->quantity;
                    $subtotal = (float)$item->price * $qty;

                    if (!isset($productSalesMap[$pId])) {
                        $productSalesMap[$pId] = [
                            'id' => $pId,
                            'name' => $pName,
                            'quantity' => 0,
                            'total' => 0.0,
                        ];
                    }
                    $productSalesMap[$pId]['quantity'] += $qty;
                    $productSalesMap[$pId]['total'] += $subtotal;
                }
            }

            $sector = $branch->sector ?? 'Sin especificar';
            $parroquia = $branch->parroquia ?? 'Sin especificar';

            $processed->push([
                'id' => $branch->id,
                'code' => $branch->code,
                'branch_name' => $branch->name,
                'route' => $branch->route,
                'address' => $branch->address,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_identification' => $client->identification,
                'has_geo' => !empty($latitude) && !empty($longitude),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geo_source' => $geoSource,
                'last_interaction_date' => $interactionDate,
                'sector' => $sector,
                'parroquia' => $parroquia,
                'total_sales' => round($branchSalesTotal, 2),
                'orders_count' => $branchOrdersCount,
            ]);
        }

        // Filtro adicional de búsqueda en los items procesados
        if ($request->search) {
            $s = strtolower(trim($request->search));
            $processed = $processed->filter(function ($item) use ($s) {
                return str_contains(strtolower($item['client_name']), $s) ||
                    str_contains(strtolower($item['branch_name']), $s) ||
                    str_contains(strtolower($item['route'] ?? ''), $s) ||
                    str_contains(strtolower($item['sector']), $s);
            });
        }

        // Desglose de Productos
        $productBreakdown = collect($productSalesMap)->values()->map(function($item) use ($totalRouteSales) {
            $item['total'] = round($item['total'], 2);
            $item['share_percentage'] = $totalRouteSales > 0 ? round(($item['total'] / $totalRouteSales) * 100, 1) : 0;
            return $item;
        })->sortByDesc('total')->values()->toArray();

        // Ventas Mensuales históricas de la Ruta (últimos 6 meses)
        $monthlySalesQuery = Order::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
                DB::raw("SUM(total) as total")
            )
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth());

        if (!empty($selectedRoutes) && !in_array('all', $selectedRoutes)) {
            $branchIdsInRoute = $branches->pluck('id')->toArray();
            $monthlySalesQuery->whereIn('branch_id', $branchIdsInRoute);
        }

        $monthlySalesRaw = $monthlySalesQuery->groupBy('ym')->orderBy('ym')->get();
        $monthlySales = [];
        foreach ($monthlySalesRaw as $m) {
            $dateObj = Carbon::createFromFormat('Y-m', $m->ym);
            $monthlySales[] = [
                'month' => $dateObj->locale('es')->shortMonthName . ' ' . $dateObj->format('Y'),
                'total' => round((float)$m->total, 2),
            ];
        }

        $topProduct = $productBreakdown[0]['name'] ?? 'N/A';

        $summary = [
            'total_sales' => round($totalRouteSales, 2),
            'total_orders' => $totalRouteOrders,
            'total_branches' => $processed->count(),
            'geolocated_count' => $processed->where('has_geo', true)->count(),
            'top_product' => $topProduct,
        ];

        return [
            'branches' => $processed->sortByDesc('total_sales')->values(),
            'available_routes' => $availableRoutes,
            'selected_routes' => $selectedRoutes,
            'product_breakdown' => $productBreakdown,
            'monthly_sales' => $monthlySales,
            'summary' => $summary,
        ];
    }

    public function ordersRoutes(Request $request)
    {
        [$query, $from, $to, $dateType, $docType, $userIds, $routes] = $this->getOrdersRoutesBaseQuery($request);

        $totalSales = (float) (clone $query)->sum('orders.total');
        $totalCount = (int) (clone $query)->count();

        // Desglose por tipo de facturación
        $invoicedQuery = (clone $query)->where(function($q) {
            $q->where('orders.is_invoiced', true)->orWhere('orders.status', 'invoiced');
        });
        $invoicedCount = (int) (clone $invoicedQuery)->count();
        $invoicedTotal = (float) (clone $invoicedQuery)->sum('orders.total');

        $preinvoicedQuery = (clone $query)->where('orders.is_preinvoiced', true);
        $preinvoicedCount = (int) (clone $preinvoicedQuery)->count();
        $preinvoicedTotal = (float) (clone $preinvoicedQuery)->sum('orders.total');

        $pendingCount = max(0, $totalCount - ($invoicedCount + $preinvoicedCount));
        $pendingTotal = max(0, $totalSales - ($invoicedTotal + $preinvoicedTotal));

        // Desglose por rutas (compatible con sql_mode=only_full_group_by)
        $subQuery = (clone $query)
            ->reorder()
            ->select('orders.total')
            ->selectRaw("COALESCE(branches.route, (SELECT b.route FROM branches b WHERE b.client_id = orders.client_id AND b.route IS NOT NULL LIMIT 1), 'SIN RUTA') as route_name");

        $routeSummaryRaw = DB::table(DB::raw("(" . $subQuery->toSql() . ") as sub_routes"))
            ->mergeBindings($subQuery->getQuery())
            ->select('route_name', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as sales_sum'))
            ->groupBy('route_name')
            ->orderByDesc('sales_sum')
            ->get();

        $routeBreakdown = $routeSummaryRaw->map(function($r) {
            return [
                'route' => $r->route_name ?? 'SIN RUTA',
                'orders_count' => (int)$r->orders_count,
                'sales_sum' => round((float)$r->sales_sum, 2),
            ];
        })->values()->toArray();

        $orderSortCol = $dateType === 'delivery_date' ? 'orders.delivery_date' : 'orders.created_at';
        $orders = (clone $query)
            ->orderByDesc($orderSortCol)
            ->orderByDesc('orders.id')
            ->paginate(30)
            ->withQueryString();

        $sellers = User::orderBy('name')->get(['id', 'name']);
        $availableRoutes = Branch::whereNotNull('route')
            ->where('route', '!=', '')
            ->distinct()
            ->orderBy('route')
            ->pluck('route')
            ->values()
            ->toArray();

        return Inertia::render('Admin/Reports/OrdersRoutes', [
            'orders' => $orders,
            'summary' => [
                'total_sales' => round($totalSales, 2),
                'total_count' => $totalCount,
                'invoiced_count' => $invoicedCount,
                'invoiced_total' => round($invoicedTotal, 2),
                'preinvoiced_count' => $preinvoicedCount,
                'preinvoiced_total' => round($preinvoicedTotal, 2),
                'pending_count' => $pendingCount,
                'pending_total' => round($pendingTotal, 2),
                'route_breakdown' => $routeBreakdown,
            ],
            'sellers' => $sellers,
            'available_routes' => $availableRoutes,
            'filters' => [
                'date_type' => $dateType,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'doc_type' => $docType,
                'user_ids' => array_values($userIds),
                'routes' => array_values($routes),
                'payment_method' => $request->payment_method ?? '',
                'status' => $request->status ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    public function exportOrdersRoutes(Request $request)
    {
        [$query, $from, $to, $dateType, $docType, $userIds, $routes] = $this->getOrdersRoutesBaseQuery($request);

        $orderSortCol = $dateType === 'delivery_date' ? 'orders.delivery_date' : 'orders.created_at';
        $orders = $query
            ->orderByDesc($orderSortCol)
            ->orderByDesc('orders.id')
            ->get();

        $csvHeader = [
            "ID Pedido",
            "Fecha Pedido",
            "Fecha Entrega",
            "Tipo Documento",
            "Ruta",
            "Cliente",
            "RUC / Cedula",
            "Sucursal",
            "Vendedor",
            "Metodo Pago",
            "Dias Credito",
            "Estado Pedido",
            "Total ($)",
        ];

        $filename = "Reporte_Pedidos_Facturas_Rutas_" . date('Ymd_His') . ".csv";

        return response()->stream(function () use ($csvHeader, $orders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $csvHeader);

            foreach ($orders as $order) {
                $docTypeLabel = 'PEDIDO';
                if ($order->is_invoiced || $order->status === 'invoiced') {
                    $docTypeLabel = 'FACTURA SRI';
                } elseif ($order->is_preinvoiced) {
                    $docTypeLabel = 'PREFACTURA';
                }

                $route = $order->computed_route ?? 'SIN RUTA';

                fputcsv($file, [
                    '#' . $order->id,
                    $order->created_at ? $order->created_at->format('d/m/Y H:i') : 'N/A',
                    $order->delivery_date ? Carbon::parse($order->delivery_date)->format('d/m/Y') : 'N/A',
                    $docTypeLabel,
                    $route,
                    $order->client ? $order->client->name : 'N/A',
                    $order->client ? ($order->client->identification ?? 'N/A') : 'N/A',
                    $order->branch ? $order->branch->name : 'Principal / Matriz',
                    $order->user ? $order->user->name : 'N/A',
                    $order->payment_method ?? 'Efectivo',
                    $order->credit_days ?? 0,
                    $order->status,
                    number_format((float)$order->total, 2, '.', ''),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function getOrdersRoutesBaseQuery(Request $request): array
    {
        $dateType = $request->input('date_type', 'created_at');
        if (!in_array($dateType, ['created_at', 'delivery_date'])) {
            $dateType = 'created_at';
        }

        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $query = Order::query()
            ->leftJoin('branches', 'branches.id', '=', 'orders.branch_id')
            ->select('orders.*')
            ->selectRaw("COALESCE(branches.route, (SELECT b.route FROM branches b WHERE b.client_id = orders.client_id AND b.route IS NOT NULL LIMIT 1), 'SIN RUTA') as computed_route")
            ->with(['client', 'branch', 'user']);

        // Filtro por rango de fecha
        if ($dateType === 'delivery_date') {
            $query->whereBetween('orders.delivery_date', [
                $from->copy()->format('Y-m-d'),
                $to->copy()->format('Y-m-d')
            ]);
        } else {
            $query->whereBetween('orders.created_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay()
            ]);
        }

        // Filtro por tipo de documento (Factura / Prefactura / Pedido)
        $docType = $request->input('doc_type', 'all');
        if ($docType === 'invoiced') {
            $query->where(function ($q) {
                $q->where('orders.is_invoiced', true)
                  ->orWhere('orders.status', 'invoiced');
            });
        } elseif ($docType === 'preinvoiced') {
            $query->where('orders.is_preinvoiced', true);
        } elseif ($docType === 'not_invoiced') {
            $query->where('orders.is_invoiced', false)
                  ->where('orders.is_preinvoiced', false)
                  ->where('orders.status', '!=', 'invoiced');
        }

        // Filtro por Múltiples Vendedores
        $userIds = $request->input('user_ids', []);
        if (!empty($userIds)) {
            if (is_string($userIds)) {
                $userIds = explode(',', $userIds);
            }
            $userIds = array_values(array_filter(array_map('intval', (array)$userIds)));
            if (!empty($userIds)) {
                $query->whereIn('orders.user_id', $userIds);
            }
        }

        // Filtro por Rutas (admite una o múltiples)
        $routes = $request->input('routes', []);
        if (!empty($routes)) {
            if (is_string($routes)) {
                $routes = explode(',', $routes);
            }
            $routes = array_values(array_filter(array_map('trim', (array)$routes)));
            if (!empty($routes) && !in_array('all', $routes)) {
                $hasNoRoute = in_array('none', $routes) || in_array('SIN RUTA', $routes);
                $cleanRoutes = array_values(array_filter($routes, fn($r) => $r !== 'none' && $r !== 'SIN RUTA'));

                $query->where(function ($q) use ($cleanRoutes, $hasNoRoute) {
                    if (!empty($cleanRoutes)) {
                        $q->whereIn(DB::raw("COALESCE(branches.route, (SELECT b.route FROM branches b WHERE b.client_id = orders.client_id AND b.route IS NOT NULL LIMIT 1))"), $cleanRoutes);
                    }
                    if ($hasNoRoute) {
                        $orMethod = !empty($cleanRoutes) ? 'orWhereNull' : 'whereNull';
                        $q->$orMethod(DB::raw("COALESCE(branches.route, (SELECT b.route FROM branches b WHERE b.client_id = orders.client_id AND b.route IS NOT NULL LIMIT 1))"));
                    }
                });
            }
        }

        // Filtro por estado
        if ($request->status) {
            $query->where('orders.status', $request->status);
        } else {
            $query->where('orders.status', '!=', 'cancelled');
        }

        // Filtro por método de pago
        if ($request->payment_method) {
            $pm = strtolower($request->payment_method);
            if ($pm === 'credito') {
                $query->where(function ($q) {
                    $q->where('orders.payment_method', 'LIKE', '%cred%')
                      ->orWhere('orders.payment_method', 'LIKE', '%créd%');
                });
            } elseif ($pm === 'efectivo') {
                $query->where(function ($q) {
                    $q->where('orders.payment_method', 'LIKE', '%efec%')
                      ->orWhereNull('orders.payment_method');
                });
            } else {
                $query->where('orders.payment_method', 'LIKE', "%{$request->payment_method}%");
            }
        }

        // Filtro por texto de búsqueda
        if ($request->search) {
            $s = trim($request->search);
            $cleanId = ltrim(str_replace('#', '', $s), '0');
            $query->where(function ($q) use ($s, $cleanId) {
                if (is_numeric($cleanId)) {
                    $q->orWhere('orders.id', (int)$cleanId);
                }
                $q->orWhereHas('client', function ($cq) use ($s) {
                    $cq->where('name', 'LIKE', "%{$s}%")
                       ->orWhere('nombre_local', 'LIKE', "%{$s}%")
                       ->orWhere('identification', 'LIKE', "%{$s}%");
                })->orWhereHas('branch', function ($bq) use ($s) {
                    $bq->where('name', 'LIKE', "%{$s}%")
                       ->orWhere('route', 'LIKE', "%{$s}%");
                });
            });
        }

        return [$query, $from, $to, $dateType, $docType, $userIds, $routes];
    }
}

