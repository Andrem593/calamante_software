<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Equipment::query();

        if ($request->search) {
            $query->where('model', 'like', "%{$request->search}%")
                  ->orWhere('capacity', 'like', "%{$request->search}%");
        }

        return Inertia::render('Admin/Equipments/Index', [
            'equipments' => $query->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Equipments/Form');
    }

    public function store(Request $request)
    {
        $request->validate([
            'model' => 'required|string|max:255',
            'capacity' => 'required|string|max:255',
        ]);

        Equipment::create($request->all());
        return redirect()->route('admin.equipments.index')->with('success', 'Equipo creado exitosamente.');
    }

    public function edit(Equipment $equipment)
    {
        return Inertia::render('Admin/Equipments/Form', ['equipment' => $equipment]);
    }

    public function update(Request $request, Equipment $equipment)
    {
        $request->validate([
            'model' => 'required|string|max:255',
            'capacity' => 'required|string|max:255',
        ]);

        $equipment->update($request->all());
        return redirect()->route('admin.equipments.index')->with('success', 'Equipo actualizado exitosamente.');
    }

    public function destroy(Equipment $equipment)
    {
        $equipment->delete();
        return redirect()->route('admin.equipments.index')->with('success', 'Equipo eliminado exitosamente.');
    }
}
