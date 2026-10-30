<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Imports\ClientsImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Artisan;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::withCount('branches')->with('branches');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('nombre_local', 'like', "%{$request->search}%")
                  ->orWhere('identification', 'like', "%{$request->search}%")
                  ->orWhere('vat_number', 'like', "%{$request->search}%");
            });
        }

        $clients = $query->latest()->paginate(15)->withQueryString();
        $clients->getCollection()->transform(function ($client) {
            $client->append('is_blocked_for_sales');
            return $client;
        });

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Clients/Form', [
            'equipments' => \App\Models\Equipment::orderBy('model')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nombre_local' => 'nullable|string|max:255',
            'identification_type' => 'nullable|string|max:20',
            'identification' => 'nullable|string|max:50|unique:clients',
            'vat_number' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'vendedor_id' => 'nullable|string|max:100',
            'vendedor_nombre' => 'nullable|string|max:255',
        ]);

        $client = Client::create($request->all());

        if ($request->has('equipment_ids')) {
            $client->equipments()->sync($request->equipment_ids);
        }

        return redirect()->route('admin.clients.index')->with('success', 'Cliente creado exitosamente.');
    }

    public function edit(Client $client)
    {
        return Inertia::render('Admin/Clients/Form', [
            'client' => $client->load(['branches', 'equipments']),
            'equipments' => \App\Models\Equipment::orderBy('model')->get(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nombre_local' => 'nullable|string|max:255',
            'identification_type' => 'nullable|string|max:20',
            'identification' => 'nullable|string|max:50|unique:clients,identification,'.$client->id,
            'vat_number' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'vendedor_id' => 'nullable|string|max:100',
            'vendedor_nombre' => 'nullable|string|max:255',
        ]);

        $client->update($request->all());

        if ($request->has('equipment_ids')) {
            $client->equipments()->sync($request->equipment_ids);
        }

        return redirect()->route('admin.clients.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('admin.clients.index')->with('success', 'Cliente eliminado.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);
        Excel::import(new ClientsImport, $request->file('file'));
        return redirect()->route('admin.clients.index')->with('success', 'Clientes importados exitosamente.');
    }

    public function sync(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $onlyInactive = $request->boolean('inactive') || $request->boolean('only_inactive');
        $onlyCredit = $request->boolean('credit') || $request->boolean('only_credit');
        $onlyVendor = $request->boolean('vendedor') || $request->boolean('vendor') || $request->boolean('only_vendor');

        if ($onlyInactive) {
            Artisan::call('sync:contifico-clients', ['--inactive' => true]);
            $output = trim(Artisan::output());
            return redirect()->route('admin.clients.index')->with('success', 'Sincronización de inactivos completada. ' . $output);
        }

        if ($onlyCredit) {
            Artisan::call('sync:contifico-clients', ['--credit' => true]);
            $output = trim(Artisan::output());
            return redirect()->route('admin.clients.index')->with('success', 'Sincronización de crédito completada. ' . $output);
        }

        if ($onlyVendor) {
            Artisan::call('sync:contifico-clients', ['--vendedor' => true]);
            $output = trim(Artisan::output());
            return redirect()->route('admin.clients.index')->with('success', 'Sincronización de vendedores asignados completada. ' . $output);
        }

        Artisan::call('sync:contifico-clients');
        $output = trim(Artisan::output());
        return redirect()->route('admin.clients.index')->with('success', 'Sincronización con Contifico completada. ' . $output);
    }

    public function searchJson(Request $request)
    {
        $query = Client::query();
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('nombre_local', 'like', "%{$request->search}%")
                  ->orWhere('identification', 'like', "%{$request->search}%");
            });
        }
        return response()->json($query->take(20)->get(['id', 'name', 'identification', 'identification_type']));
    }

    public function merge(Request $request)
    {
        $request->validate([
            'source_client_id' => 'required|exists:clients,id',
            'target_client_id' => 'required|exists:clients,id|different:source_client_id',
        ]);

        Client::mergeClients($request->source_client_id, $request->target_client_id);

        return redirect()->route('admin.clients.index')->with('success', 'Clientes fusionados exitosamente.');
    }

    public function unblock(Request $request, Client $client)
    {
        $request->validate([
            'days' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $days = (int)$request->days;
        $unblockedUntil = \Carbon\Carbon::now()->addDays($days)->endOfDay();

        $client->update([
            'unblocked_until' => $unblockedUntil,
            'block_reason' => $request->reason ?? "Desbloqueado temporalmente por {$days} día(s) por el administrador.",
        ]);

        return redirect()->back()->with('success', "Cliente '{$client->name}' desbloqueado temporalmente por {$days} día(s) (hasta {$unblockedUntil->format('d/m/Y H:i')}).");
    }

    public function toggleBlock(Request $request, Client $client)
    {
        $request->validate([
            'is_blocked' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $isBlocked = $request->boolean('is_blocked');

        $client->update([
            'is_blocked' => $isBlocked,
            'unblocked_until' => $isBlocked ? null : $client->unblocked_until,
            'block_reason' => $request->reason ?? ($isBlocked ? 'Bloqueado manualmente por el administrador' : 'Desbloqueado manualmente por el administrador'),
        ]);

        $statusText = $isBlocked ? 'bloqueado' : 'desbloqueado';
        return redirect()->back()->with('success', "El cliente '{$client->name}' ha sido {$statusText}.");
    }
}

