<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ContificoService;
use Illuminate\Console\Command;

class SyncContificoClients extends Command
{
    protected $signature = 'sync:contifico-clients';
    protected $description = 'Sincroniza clientes desde Contifico';

    public function handle(ContificoService $contifico)
    {
        $this->info('Iniciando sincronización de clientes...');
        
        $clients = $contifico->fetchClients();
        
        if (empty($clients)) {
            $this->error('No se obtuvieron clientes de Contifico.');
            return;
        }

        // $this->info("Total clientes recibidos: " . count($clients));
        
        $count = 0;
        $processed = 0;
        foreach ($clients as $cData) {
            // Regla: Solo tomar lo que es_cliente = true
            if (($cData['es_cliente'] ?? false) !== true) continue;

            $nombre = $cData['razon_social'] ?? $cData['nombre_comercial'] ?? 'Sin Nombre';
            $tipoPersona = $cData['tipo'] ?? 'N'; // N = Natural, J = Jurídico
            
            $cedula = $cData['cedula'] ?? '';
            $ruc = $cData['ruc'] ?? '';

            // Regla de identificación:
            // SI ES TIPO N COGER COMO IDENTIFICACIÓN cedula, tipo "Cedula"
            // SI ES JURIDICO (J). escoger ruc. tipo "RUC".
            $identificacion = '';
            $tipoIdentificacion = '';

            if ($tipoPersona === 'N') {
                $identificacion = $cedula;
                $tipoIdentificacion = 'Cedula';
            } else {
                $identificacion = $ruc;
                $tipoIdentificacion = 'RUC';
            }

            if (empty($identificacion)) continue;

            $client = Client::updateOrCreate(
                ['identification' => $identificacion],
                [
                    'name' => $nombre,
                    'contifico_id' => $cData['id'] ?? null,
                    'nombre_local' => $cData['nombre_comercial'] ?? null,
                    'company_name' => $cData['nombre_comercial'] ?? null,
                    'email' => $cData['email'] ?? null,
                    'address' => $cData['direccion'] ?? null,
                    'phone' => $cData['telefonos'] ?? null,
                    'identification_type' => $tipoIdentificacion,
                ]
            );

            $processed++;

            if ($client->wasRecentlyCreated) {
                $this->line(" - Nuevo Cliente: [{$client->identification}] {$client->name}");
                $count++;
            }
        }

        $this->info("Sincronización completada. Se procesaron {$processed} clientes.");
        $this->info("Se crearon {$count} nuevos clientes.");
    }
}
