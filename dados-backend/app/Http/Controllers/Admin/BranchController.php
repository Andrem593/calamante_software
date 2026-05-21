<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'route'      => 'nullable|string|max:100',
            'address'    => 'nullable|string',
            'email'      => 'nullable|email|max:255',
        ]);

        $code = $this->generateCode($request->brand_name, $request->name);

        $client->branches()->create([
            'name'       => $request->name,
            'code'       => $code,
            'brand_name' => $request->brand_name,
            'route'      => $request->route,
            'address'    => $request->address,
            'email'      => $request->email,
        ]);

        return redirect()->route('admin.clients.edit', $client)
            ->with('success', "Sucursal agregada con código {$code}.");
    }

    public function update(Request $request, Branch $branch)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'route'      => 'nullable|string|max:100',
            'address'    => 'nullable|string',
            'email'      => 'nullable|email|max:255',
        ]);

        $branch->update($request->only('name', 'brand_name', 'route', 'address', 'email'));
        return back()->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Branch $branch)
    {
        $client_id = $branch->client_id;
        $branch->delete();
        return redirect()->route('admin.clients.edit', $client_id)
            ->with('success', 'Sucursal eliminada.');
    }

    /**
     * Genera código único: NNNNN-XX-YY
     *  NNNNN = secuencial global de sucursales (5 dígitos, empieza en 00001)
     *  XX    = 2 primeras letras del brand_name (local) en mayúscula
     *  YY    = 2 primeras letras del nombre de sucursal en mayúscula
     *
     * Ejemplo: branch_name="Fybeca Ceibos", brand_name="Fybeca"
     *   → 00469-FY-FY
     */
    public static function generateCode(?string $brandName, string $branchName): string
    {
        $seq = str_pad(Branch::max('id') + 1, 5, '0', STR_PAD_LEFT);

        // 3 letras de la marca (o nombre si marca es nula)
        $brandText = $brandName ?? $branchName;
        $brand = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $brandText), 0, 3));
        $brand = str_pad($brand, 3, 'X');

        // Nombre de sucursal completo en mayúsculas (como en la captura 2)
        $local = strtoupper($branchName);

        return "{$seq}-{$brand}-{$local}";
    }
}
