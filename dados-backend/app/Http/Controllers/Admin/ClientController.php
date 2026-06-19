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

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Clients/Form');
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
        ]);

        Client::create($request->all());
        return redirect()->route('admin.clients.index')->with('success', 'Cliente creado exitosamente.');
    }

    public function edit(Client $client)
    {
        return Inertia::render('Admin/Clients/Form', [
            'client' => $client->load('branches'),
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
        ]);

        $client->update($request->all());
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

    public function sync()
    {
        Artisan::call('sync:contifico-clients');
        return redirect()->route('admin.clients.index')->with('success', 'Sincronización con Contifico completada.');
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
}
