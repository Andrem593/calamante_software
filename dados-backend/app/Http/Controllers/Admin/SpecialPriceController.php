<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialPrice;
use App\Imports\SpecialPricesImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SpecialPriceController extends Controller
{
    public function index(Request $request)
    {
        $query = SpecialPrice::with(['client', 'product']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('nombre_local', 'like', "%{$search}%")
                       ->orWhere('identification', 'like', "%{$search}%");
                })->orWhereHas('product', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                });
            });
        }

        return Inertia::render('Admin/SpecialPrices/Index', [
            'specialPrices' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new SpecialPricesImport, $request->file('file'));
            return redirect()->route('admin.special-prices.index')
                ->with('success', 'Precios especiales importados exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.special-prices.index')
                ->with('error', 'Error al importar archivo: ' . $e->getMessage());
        }
    }

    public function destroy(SpecialPrice $specialPrice)
    {
        $specialPrice->delete();
        return redirect()->route('admin.special-prices.index')
            ->with('success', 'Precio especial eliminado.');
    }
}
