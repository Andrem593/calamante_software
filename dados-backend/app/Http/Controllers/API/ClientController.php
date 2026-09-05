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
        })->orderBy('name')->get(['id', 'name', 'nombre_local', 'identification', 'credit_limit', 'credit_days']);
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
                    ->orWhere('identification', 'LIKE', "%{$q}%");
            })
            ->orderBy('nombre_local')
            ->limit(10)
            ->get(['id', 'name', 'nombre_local', 'company_name', 'identification', 'identification_type', 'address', 'email', 'phone', 'credit_limit', 'credit_days']);
    }

    /**
     * Retorna las sucursales de un cliente.
     */
    public function branches($id)
    {
        $client = Client::findOrFail($id);
        $branches = $client->branches()->select('id', 'name', 'code', 'address', 'email')->get();
        return response()->json($branches);
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
                        if ($vencimiento->lt($today)) {
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

        return response()->json([
            'client_id' => $client->id,
            'identification' => $client->identification,
            'credit_limit' => $creditLimit,
            'credit_days' => $creditDays,
            'has_credit_configured' => $hasCreditConfigured,
            'total_pendiente' => $totalPendiente,
            'total_vencido' => $totalVencido,
            'count_pendientes' => $countPendientes,
            'count_vencidos' => $countVencidos,
            'available_credit' => $availableCredit,
            'documents' => $processedDocs,
        ]);
    }
}

