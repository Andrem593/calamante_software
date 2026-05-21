<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'CONTIFICO_API_KEY' => Setting::get('CONTIFICO_API_KEY'),
                'CONTIFICO_API_TOKEN' => Setting::get('CONTIFICO_API_TOKEN'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'CONTIFICO_API_KEY' => 'nullable|string',
            'CONTIFICO_API_TOKEN' => 'nullable|string',
        ]);

        foreach ($request->only(['CONTIFICO_API_KEY', 'CONTIFICO_API_TOKEN']) as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->back()->with('success', 'Configuraciones guardadas exitosamente.');
    }
}
