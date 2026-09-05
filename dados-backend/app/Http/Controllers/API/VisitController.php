<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $visits = Visit::where('user_id', $request->user()->id)
            ->with(['client', 'branch'])
            ->latest()
            ->take(20)
            ->get();

        return response()->json($visits);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'branch_id' => 'nullable|exists:branches,id',
            'comment' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $visit = Visit::create([
            'user_id' => $request->user()->id,
            'client_id' => $request->client_id,
            'branch_id' => $request->branch_id,
            'comment' => $request->comment,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Visita registrada exitosamente.',
            'visit' => $visit->load('client', 'branch'),
        ], 201);
    }
}
