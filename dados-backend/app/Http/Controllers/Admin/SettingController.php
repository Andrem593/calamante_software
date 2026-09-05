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
                'GOOGLE_MAPS_API_KEY' => Setting::get('GOOGLE_MAPS_API_KEY') ?: env('GOOGLE_MAPS_API_KEY'),
                'LATEST_APP_VERSION' => Setting::get('LATEST_APP_VERSION', '1.0.4'),
                'MIN_REQUIRED_APP_VERSION' => Setting::get('MIN_REQUIRED_APP_VERSION', '1.0.4'),
                'APP_APK_URL' => Setting::get('APP_APK_URL', 'https://app.grupo-dados.com/downloads/dados-app.apk'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'CONTIFICO_API_KEY' => 'nullable|string',
            'CONTIFICO_API_TOKEN' => 'nullable|string',
            'GOOGLE_MAPS_API_KEY' => 'nullable|string',
            'LATEST_APP_VERSION' => 'nullable|string',
            'MIN_REQUIRED_APP_VERSION' => 'nullable|string',
            'APP_APK_URL' => 'nullable|string',
            'apk_file' => 'nullable|file|max:102400', // Max 100MB
        ]);

        foreach ($request->only(['CONTIFICO_API_KEY', 'CONTIFICO_API_TOKEN', 'GOOGLE_MAPS_API_KEY', 'LATEST_APP_VERSION', 'MIN_REQUIRED_APP_VERSION', 'APP_APK_URL']) as $key => $value) {
            Setting::set($key, $value);
        }

        if ($request->hasFile('apk_file')) {
            if ($request->user()->role !== 'superadmin') {
                return redirect()->back()->with('error', 'No tienes permisos para subir archivos APK.');
            }

            $file = $request->file('apk_file');
            if ($file->getClientOriginalExtension() !== 'apk') {
                return redirect()->back()->with('error', 'El archivo debe tener extensión .apk');
            }

            $destinationPath = public_path('downloads');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $file->move($destinationPath, 'dados-app.apk');
            
            // Generar URL absoluta para la descarga
            Setting::set('APP_APK_URL', asset('downloads/dados-app.apk'));
        }

        return redirect()->back()->with('success', 'Configuraciones guardadas exitosamente.');
    }
}
