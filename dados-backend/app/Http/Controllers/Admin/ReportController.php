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
            ],
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

    protected function getProcessedBranchesGeolocation(Request $request, GoogleGeocodingService $geocoding): array
    {
        $branches = Branch::with(['client'])->get();

        $processed = collect();

        foreach ($branches as $branch) {
            $client = $branch->client;
            if (!$client) continue;

            // Obtener la última venta con coordenadas para esta sucursal (o cliente)
            $lastOrder = Order::where('branch_id', $branch->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastOrder) {
                $lastOrder = Order::where('client_id', $client->id)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            // Obtener la última visita con coordenadas para esta sucursal (o cliente)
            $lastVisit = Visit::where('branch_id', $branch->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastVisit) {
                $lastVisit = Visit::where('client_id', $client->id)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            // Comparar y elegir la geolocalización más reciente
            $latitude = null;
            $longitude = null;
            $geoSource = 'Sin coordenadas';
            $interactionDate = null;

            $orderTime = $lastOrder ? Carbon::parse($lastOrder->created_at) : null;
            $visitTime = $lastVisit ? Carbon::parse($lastVisit->created_at) : null;

            if ($orderTime && $visitTime) {
                if ($orderTime->gt($visitTime)) {
                    $latitude = (float)$lastOrder->latitude;
                    $longitude = (float)$lastOrder->longitude;
                    $geoSource = "Última Venta (#{$lastOrder->id})";
                    $interactionDate = $orderTime->format('d/m/Y H:i');
                } else {
                    $latitude = (float)$lastVisit->latitude;
                    $longitude = (float)$lastVisit->longitude;
                    $geoSource = "Última Visita (#{$lastVisit->id})";
                    $interactionDate = $visitTime->format('d/m/Y H:i');
                }
            } elseif ($orderTime) {
                $latitude = (float)$lastOrder->latitude;
                $longitude = (float)$lastOrder->longitude;
                $geoSource = "Última Venta (#{$lastOrder->id})";
                $interactionDate = $orderTime->format('d/m/Y H:i');
            } elseif ($visitTime) {
                $latitude = (float)$lastVisit->latitude;
                $longitude = (float)$lastVisit->longitude;
                $geoSource = "Última Visita (#{$lastVisit->id})";
                $interactionDate = $visitTime->format('d/m/Y H:i');
            } elseif (!empty($branch->latitude) && !empty($branch->longitude)) {
                $latitude = (float)$branch->latitude;
                $longitude = (float)$branch->longitude;
                $geoSource = "Ubicación de Sucursal";
                $interactionDate = $branch->updated_at ? Carbon::parse($branch->updated_at)->format('d/m/Y') : 'N/A';
            }

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

    public function presaleDirectSale(Request $request)
    {
        $data = $this->getPresaleDirectSaleData($request);

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

    public function exportPresaleDirectSale(Request $request)
    {
        $data = $this->getPresaleDirectSaleData($request);
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

    private function getPresaleDirectSaleData(Request $request)
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

        // Sellers: Users with role seller OR who have created orders/visits
        $sellers = User::where(function($q) {
                $q->where('role', 'seller')
                  ->orWhereHas('orders')
                  ->orWhereHas('visits');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Prefetch visits grouped by user_id
        $visitsGrouped = Visit::whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Prefetch orders with items in range (exclude cancelled)
        $orders = Order::with('items')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->get();

        $ordersGrouped = $orders->groupBy('user_id');

        $reportData = [];

        $totals = [
            'visits' => 0,
            'orders_count' => 0,
            'invoiced_amount' => 0.0,
            'preinvoiced_amount' => 0.0,
            'cash_amount' => 0.0,
            'credit_amount' => 0.0,
            'transfer_amount' => 0.0,
            'total_amount' => 0.0,
            'product_quantities' => [],
            'total_items' => 0,
        ];

        foreach ($products as $p) {
            $totals['product_quantities'][$p->id] = 0;
        }

        foreach ($sellers as $seller) {
            $userVisits = (int)($visitsGrouped[$seller->id] ?? 0);
            $userOrders = $ordersGrouped->get($seller->id, collect());

            $ordersCount = $userOrders->count();
            $invoicedAmount = 0.0;
            $preinvoicedAmount = 0.0;
            $cashAmount = 0.0;
            $creditAmount = 0.0;
            $transferAmount = 0.0;

            $productQuantities = [];
            foreach ($products as $p) {
                $productQuantities[$p->id] = 0;
            }
            $totalItems = 0;

            foreach ($userOrders as $ord) {
                $ordTotal = (float)$ord->total;
                
                // Invoiced vs Preinvoiced
                if ($ord->is_invoiced) {
                    $invoicedAmount += $ordTotal;
                } else {
                    $preinvoicedAmount += $ordTotal;
                }

                // Payment method
                $pm = mb_strtolower($ord->payment_method ?? '');
                if (str_contains($pm, 'cred') || str_contains($pm, 'créd') || ($ord->credit_days ?? 0) > 0) {
                    $creditAmount += $ordTotal;
                } elseif (str_contains($pm, 'transf') || str_contains($pm, 'banco') || str_contains($pm, 'deposito') || str_contains($pm, 'depósito')) {
                    $transferAmount += $ordTotal;
                } else {
                    $cashAmount += $ordTotal;
                }

                // Order items
                foreach ($ord->items as $item) {
                    $pId = $item->product_id;
                    $qty = (int)$item->quantity;
                    if (isset($productQuantities[$pId])) {
                        $productQuantities[$pId] += $qty;
                    } else {
                        $productQuantities[$pId] = $qty;
                    }
                    $totalItems += $qty;
                }
            }

            $totalAmount = $invoicedAmount + $preinvoicedAmount;

            $reportData[] = [
                'user_id' => $seller->id,
                'seller_name' => $seller->name,
                'visits' => $userVisits,
                'orders_count' => $ordersCount,
                'invoiced_amount' => round($invoicedAmount, 2),
                'preinvoiced_amount' => round($preinvoicedAmount, 2),
                'cash_amount' => round($cashAmount, 2),
                'credit_amount' => round($creditAmount, 2),
                'transfer_amount' => round($transferAmount, 2),
                'total_amount' => round($totalAmount, 2),
                'product_quantities' => $productQuantities,
                'total_items' => $totalItems,
            ];

            // Accumulate global totals
            $totals['visits'] += $userVisits;
            $totals['orders_count'] += $ordersCount;
            $totals['invoiced_amount'] += $invoicedAmount;
            $totals['preinvoiced_amount'] += $preinvoicedAmount;
            $totals['cash_amount'] += $cashAmount;
            $totals['credit_amount'] += $creditAmount;
            $totals['transfer_amount'] += $transferAmount;
            $totals['total_amount'] += $totalAmount;
            $totals['total_items'] += $totalItems;

            foreach ($productQuantities as $pId => $qty) {
                if (!isset($totals['product_quantities'][$pId])) {
                    $totals['product_quantities'][$pId] = 0;
                }
                $totals['product_quantities'][$pId] += $qty;
            }
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
}

