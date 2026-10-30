<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return Client::where(function ($q) {
            $q->where('is_active', true)->orWhere('is_active', 1)->orWhereNull('is_active');
        })->orderBy('name')->get(['id', 'name', 'nombre_local', 'identification', 'credit_limit', 'credit_days', 'is_blocked', 'unblocked_until']);
    }

    public function show($id)
    {
        return Client::with(['branches' => function($query) {
            $query->select('id', 'client_id', 'name', 'code', 'address', 'email');
        }])->findOrFail($id);
    }

    /**
     * Búsqueda de clientes por nombre o identificación.
     * Retorna máximo 10 resultados.
     */
    public function search(Request $request)
    {
        $q = $request->query('q', '');

        if (strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        return Client::where(function ($qFilter) {
                $qFilter->where('is_active', true)->orWhere('is_active', 1)->orWhereNull('is_active');
            })
            ->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('nombre_local', 'LIKE', "%{$q}%")
                    ->orWhere('company_name', 'LIKE', "%{$q}%")
                    ->orWhere('identification', 'LIKE', "%{$q}%");
            })
            ->orderByRaw('COALESCE(NULLIF(nombre_local, ""), NULLIF(company_name, ""), name) ASC')
            ->limit(10)
            ->get(['id', 'name', 'nombre_local', 'company_name', 'identification', 'identification_type', 'address', 'email', 'phone', 'credit_limit', 'credit_days', 'is_blocked', 'unblocked_until']);
    }

    /**
     * Retorna las sucursales de un cliente.
     */
    public function branches($id)
    {
        $client = Client::findOrFail($id);
        $branches = $client->branches()->select([
            'id', 'client_id', 'name', 'code', 'brand_name', 'address',
            'phone', 'email', 'latitude', 'longitude', 'sector',
            'parroquia', 'google_address', 'geocoded_at'
        ])->get();
        return response()->json($branches);
    }

    /**
     * Guarda o actualiza el censo / geolocalización de una sucursal para un cliente.
     */
    public function saveBranchCensus(Request $request, $id = null)
    {
        $clientId = $id ?? $request->input('client_id');

        $validated = $request->validate([
            'client_id' => 'required_without:branch_id|nullable|exists:clients,id',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50',
            'brand_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'sector' => 'nullable|string|max:255',
            'parroquia' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if (!$clientId && !empty($validated['branch_id'])) {
            $existingBranch = \App\Models\Branch::find($validated['branch_id']);
            if ($existingBranch) {
                $clientId = $existingBranch->client_id;
            }
        }

        $client = Client::findOrFail($clientId);

        if (!empty($validated['branch_id'])) {
            $branch = \App\Models\Branch::where('client_id', $client->id)
                ->where('id', $validated['branch_id'])
                ->firstOrFail();

            // La ubicación GPS SIEMPRE se actualiza en el censo
            $updateData = [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'geocoded_at' => now(),
            ];

            // Datos sensibles protegidos: 'name', 'code', 'brand_name' no se alteran si ya están definidos
            if (empty($branch->name) && $request->filled('name')) {
                $updateData['name'] = $validated['name'];
            }
            if (empty($branch->code) && $request->filled('code')) {
                $updateData['code'] = $validated['code'];
            }
            if (empty($branch->brand_name) && $request->filled('brand_name')) {
                $updateData['brand_name'] = $validated['brand_name'];
            }

            // Datos de referencia geográfica y contacto (sector, parroquia, etc.)
            if ($request->filled('sector')) $updateData['sector'] = $validated['sector'];
            if ($request->filled('parroquia')) $updateData['parroquia'] = $validated['parroquia'];
            if ($request->filled('address')) $updateData['address'] = $validated['address'];
            if ($request->filled('phone')) $updateData['phone'] = $validated['phone'];
            if ($request->filled('email')) $updateData['email'] = $validated['email'];

            $branch->update($updateData);
        } else {
            $branch = \App\Models\Branch::create([
                'client_id' => $client->id,
                'name' => $validated['name'] ?? ($client->nombre_local ?: $client->name . ' - Matriz'),
                'code' => $validated['code'] ?? null,
                'brand_name' => $validated['brand_name'] ?? null,
                'address' => $validated['address'] ?? $client->address,
                'sector' => $validated['sector'] ?? null,
                'parroquia' => $validated['parroquia'] ?? null,
                'phone' => $validated['phone'] ?? $client->phone,
                'email' => $validated['email'] ?? $client->email,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'geocoded_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Censo de sucursal guardado exitosamente.',
            'branch' => $branch->fresh(),
        ], 200);
    }

    /**
     * Retorna el catálogo completo de equipos.
     */
    public function getEquipments()
    {
        return response()->json(\App\Models\Equipment::orderBy('model')->get(['id', 'model', 'capacity']));
    }

    /**
     * Retorna los equipos asociados a un cliente específico (opcionalmente filtrado por sucursal).
     */
    public function getClientEquipments(Request $request, $id)
    {
        $client = Client::findOrFail($id);
        $branchId = $request->query('branch_id');
        
        $query = $client->equipments();
        if ($branchId !== null && $branchId !== '') {
            if ($branchId === 'null') {
                $query->wherePivotNull('branch_id');
            } else {
                $query->wherePivot('branch_id', $branchId);
            }
        }
        
        return response()->json($query->get(['equipments.id', 'model', 'capacity', 'client_equipment.branch_id']));
    }

    /**
     * Sincroniza la lista de equipos asociados a un cliente y una sucursal específica.
     */
    public function syncClientEquipments(Request $request, $id)
    {
        $request->validate([
            'equipment_ids' => 'present|array',
            'equipment_ids.*' => 'integer|exists:equipments,id',
            'branch_id' => 'nullable|integer|exists:branches,id'
        ]);

        $client = Client::findOrFail($id);
        $branchId = $request->branch_id;

        // Limpiar equipos asociados para esta sucursal y cliente en particular
        $query = \Illuminate\Support\Facades\DB::table('client_equipment')
            ->where('client_id', $client->id);
        
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }
        $query->delete();

        // Registrar nuevas asociaciones
        foreach ($request->equipment_ids as $eqId) {
            \Illuminate\Support\Facades\DB::table('client_equipment')->insert([
                'client_id' => $client->id,
                'branch_id' => $branchId,
                'equipment_id' => $eqId,
            ]);
        }

        return response()->json([
            'success' => true, 
            'message' => 'Equipos del cliente/sucursal actualizados con éxito.'
        ]);
    }

    /**
     * Retorna el estado de crédito y facturas pendientes desde Contifico.
     */
    public function getCreditStatus($id)
    {
        $client = Client::findOrFail($id);

        $creditLimit = $client->credit_limit !== null ? (float)$client->credit_limit : null;
        $creditDays = $client->credit_days !== null ? (int)$client->credit_days : null;

        $hasCreditConfigured = ($creditLimit !== null && $creditLimit > 0) && ($creditDays !== null && $creditDays > 0);

        if (!$client->identification) {
            return response()->json([
                'client_id' => $client->id,
                'identification' => null,
                'credit_limit' => $creditLimit,
                'credit_days' => $creditDays,
                'has_credit_configured' => $hasCreditConfigured,
                'total_pendiente' => 0.0,
                'total_vencido' => 0.0,
                'count_pendientes' => 0,
                'count_vencidos' => 0,
                'available_credit' => $creditLimit,
                'documents' => []
            ]);
        }

        /** @var \App\Services\ContificoService $contificoService */
        $contificoService = app(\App\Services\ContificoService::class);
        $rawDocs = $contificoService->getPendingDocuments($client->identification);

        $totalPendiente = 0.0;
        $totalVencido = 0.0;
        $countPendientes = 0;
        $countVencidos = 0;
        $processedDocs = [];

        $today = \Carbon\Carbon::today();

        $isCashClient = empty($client->credit_days) || $client->credit_days <= 0;
        $graceDays = $isCashClient ? 3 : 0;

        if (is_array($rawDocs)) {
            foreach ($rawDocs as $doc) {
                $saldo = isset($doc['saldo']) && $doc['saldo'] !== null
                    ? (float)$doc['saldo']
                    : (isset($doc['total']) ? (float)$doc['total'] : 0.0);

                $fechaVencimientoStr = $doc['fecha_vencimiento'] ?? null;
                $isOverdue = false;
                $daysOverdue = 0;

                if ($fechaVencimientoStr) {
                    try {
                        $vencimiento = \Carbon\Carbon::createFromFormat('d/m/Y', $fechaVencimientoStr)->startOfDay();
                        if ($vencimiento->copy()->addDays($graceDays)->lt($today)) {
                            $isOverdue = true;
                            $daysOverdue = $today->diffInDays($vencimiento);
                            $totalVencido += $saldo;
                            $countVencidos++;
                        }
                    } catch (\Exception $e) {
                        // Si falla el formato de la fecha, no marcar como vencido pero mantener en el total pendiente
                    }
                }

                $totalPendiente += $saldo;
                $countPendientes++;

                $processedDocs[] = [
                    'id' => $doc['id'] ?? null,
                    'documento' => $doc['documento'] ?? null,
                    'fecha_emision' => $doc['fecha_emision'] ?? null,
                    'fecha_vencimiento' => $fechaVencimientoStr,
                    'total' => isset($doc['total']) ? (float)$doc['total'] : 0.0,
                    'saldo' => $saldo,
                    'is_overdue' => $isOverdue,
                    'days_overdue' => $daysOverdue,
                ];
            }
        }

        $totalPendiente = round($totalPendiente, 2);
        $totalVencido = round($totalVencido, 2);
        $availableCredit = $creditLimit !== null ? round(max(0, $creditLimit - $totalPendiente), 2) : null;
        $isBlockedForSales = $client->isBlockedForSales();

        return response()->json([
            'client_id' => $client->id,
            'identification' => $client->identification,
            'credit_limit' => $creditLimit,
            'credit_days' => $creditDays,
            'has_credit_configured' => $hasCreditConfigured,
            'is_blocked' => (bool)$client->is_blocked,
            'unblocked_until' => $client->unblocked_until ? $client->unblocked_until->toIso8601String() : null,
            'unblock_time_remaining' => $client->unblock_time_remaining,
            'is_blocked_for_sales' => $isBlockedForSales,
            'total_pendiente' => $totalPendiente,
            'total_vencido' => $totalVencido,
            'count_pendientes' => $countPendientes,
            'count_vencidos' => $countVencidos,
            'available_credit' => $availableCredit,
            'documents' => $processedDocs,
        ]);
    }
}


