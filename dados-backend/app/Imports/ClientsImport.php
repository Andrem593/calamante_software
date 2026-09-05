<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Branch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Extraer identificación (RUC, Cédula o identificacion)
            $ruc = isset($row['ruc']) && !empty(trim((string)$row['ruc'])) ? trim((string)$row['ruc']) : null;
            $cedula = isset($row['cedula']) && !empty(trim((string)$row['cedula'])) ? trim((string)$row['cedula']) : null;
            $identificacion = $ruc ?? $cedula ?? (isset($row['identificacion']) && !empty(trim((string)$row['identificacion'])) ? trim((string)$row['identificacion']) : null);

            $name = $row['razon_social'] ?? $row['persona'] ?? $row['nombre'] ?? $identificacion;
            
            if (empty($name) && empty($identificacion)) {
                continue;
            }

            // Tipo de identificación
            $idType = $row['tipo'] ?? ($ruc ? 'RUC' : ($cedula ? 'Cédula' : 'RUC'));

            // Nombre comercial
            $nombreComercial = $row['nombre_comercial'] ?? $row['nombre_local'] ?? null;

            // Crédito
            $creditLimit = null;
            if (isset($row['monto_credito']) && $row['monto_credito'] !== '' && $row['monto_credito'] !== null) {
                $creditLimit = floatval(str_replace(',', '.', (string)$row['monto_credito']));
            } elseif (isset($row['credit_limit']) && $row['credit_limit'] !== '' && $row['credit_limit'] !== null) {
                $creditLimit = floatval(str_replace(',', '.', (string)$row['credit_limit']));
            }

            $creditDays = null;
            if (isset($row['dias_credito']) && $row['dias_credito'] !== '' && $row['dias_credito'] !== null) {
                $creditDays = intval($row['dias_credito']);
            } elseif (isset($row['credit_days']) && $row['credit_days'] !== '' && $row['credit_days'] !== null) {
                $creditDays = intval($row['credit_days']);
            }

            // Buscar por identificación o por nombre si no hay identificación
            if ($identificacion) {
                $client = Client::where('identification', $identificacion)->first();
            } else {
                $client = Client::where('name', $name)->first();
            }

            $clientData = [
                'name'                => $name ?: ($identificacion ?? 'Cliente Sin Nombre'),
                'nombre_local'        => $nombreComercial,
                'identification_type' => $idType,
                'identification'      => $identificacion,
                'vat_number'          => $identificacion,
                'company_name'        => $nombreComercial,
            ];

            if ($creditLimit !== null) {
                $clientData['credit_limit'] = $creditLimit;
            }
            if ($creditDays !== null) {
                $clientData['credit_days'] = $creditDays;
            }

            if ($client) {
                // Al actualizar un cliente existente, solo se modifican los campos que traen valor en el Excel.
                // Los campos vacíos o no incluidos CONSERVAN su información original y NO se borran.
                $updateData = array_filter([
                    'name'                => $name,
                    'nombre_local'        => $nombreComercial,
                    'identification_type' => $idType,
                    'identification'      => $identificacion,
                    'vat_number'          => $identificacion,
                    'company_name'        => $nombreComercial,
                    'credit_limit'        => $creditLimit,
                    'credit_days'         => $creditDays,
                ], fn($val) => $val !== null && $val !== '');

                if (!empty($updateData)) {
                    $client->update($updateData);
                }
            } else {
                // Si es un cliente nuevo, se crea con los datos disponibles
                $client = Client::create([
                    'name'                => $name ?: ($identificacion ?? 'Cliente Sin Nombre'),
                    'nombre_local'        => $nombreComercial,
                    'identification_type' => $idType ?: 'RUC',
                    'identification'      => $identificacion,
                    'vat_number'          => $identificacion,
                    'company_name'        => $nombreComercial,
                    'credit_limit'        => $creditLimit,
                    'credit_days'         => $creditDays,
                ]);
            }

            // Procesar sucursal/local si se especifica en el Excel
            $branchName = $row['cli_nombre_loca'] ?? $row['cli_nombre_local'] ?? $row['sucursales'] ?? $row['nombre_local'] ?? null;
            $branchEmail = $row['cli_correo_sucu'] ?? $row['cli_correo_sucursal'] ?? $row['email_sucursal'] ?? null;

            if ($branchName && trim((string)$branchName) !== '') {
                $branchNameClean = trim((string)$branchName);
                
                $branch = Branch::where('client_id', $client->id)
                    ->where('name', $branchNameClean)
                    ->first();

                $branchData = array_filter([
                    'brand_name' => $nombreComercial,
                    'code'       => $row['codigo'] ?? null,
                    'route'      => $row['ruta'] ?? null,
                    'email'      => $branchEmail,
                ], fn($val) => $val !== null && $val !== '');

                if ($branch) {
                    if (!empty($branchData)) {
                        $branch->update($branchData);
                    }
                } else {
                    Branch::create(array_merge([
                        'client_id'  => $client->id,
                        'name'       => $branchNameClean,
                        'brand_name' => $nombreComercial ?? $branchNameClean,
                    ], $branchData));
                }
            }
        }
    }
}
