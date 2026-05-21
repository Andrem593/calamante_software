<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return Client::orderBy('name')->get(['id', 'name', 'nombre_local', 'identification']);
    }

    public function show($id)
    {
        return Client::with(['branches' => function($query) {
            $query->select('id', 'client_id', 'name', 'code', 'address', 'email');
        }])->findOrFail($id);
    }

    /**
     * Búsqueda de clientes por nombre o identificación.
     * Retorna máximo 5 resultados.
     */
    public function search(Request $request)
    {
        $q = $request->query('q', '');

        if (strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        return Client::where('name', 'LIKE', "%{$q}%")
            ->orWhere('nombre_local', 'LIKE', "%{$q}%")
            ->orWhere('identification', 'LIKE', "%{$q}%")
            ->orderBy('nombre_local')
            ->limit(10)
            ->get(['id', 'name', 'nombre_local', 'company_name', 'identification', 'identification_type', 'address', 'email']);
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
}
