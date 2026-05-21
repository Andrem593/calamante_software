<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Branch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ClientsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Match columns: TIPO, IDENTIFICACION, CODIGO, Persona, Nombre Local, SUCURSALES, Ruta
            if (empty($row['persona']) && empty($row['identificacion'])) continue;

            $client = Client::updateOrCreate(
                ['identification' => $row['identificacion'] ?? null],
                [
                    'name'                => $row['persona'] ?? $row['identificacion'],
                    'nombre_local'        => $row['nombre_local'] ?? null,
                    'identification_type' => $row['tipo'] ?? 'RUC',
                    'identification'      => $row['identificacion'] ?? null, // Added to ensure it's set on creation
                    'vat_number'          => $row['identificacion'] ?? null,
                    'company_name'        => $row['nombre_local'] ?? null,
                ]
            );

            // If there's a branch (local), create or update it
            $branchName = $row['sucursales'] ?? $row['nombre_local'] ?? null;
            if ($branchName) {
                Branch::firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'name'      => $branchName,
                    ],
                    [
                        'code'       => $row['codigo'] ?? null,
                        'brand_name' => $row['nombre_local'] ?? null,
                        'route'      => $row['ruta'] ?? null,
                    ]
                );
            }
        }
    }
}
