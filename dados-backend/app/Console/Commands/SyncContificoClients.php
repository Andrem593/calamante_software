<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ContificoService;
use Illuminate\Console\Command;

class SyncContificoClients extends Command
{
    protected $signature = 'sync:contifico-clients {--inactive : Sincronizar solo clientes inactivos} {--only-inactive : Sincronizar solo clientes inactivos} {--credit : Sincronizar cupo y días de crédito} {--vendor : Sincronizar vendedores asignados} {--vendedor : Sincronizar vendedores asignados}';
    protected $description = 'Sincroniza clientes desde Contifico';

    public function handle(ContificoService $contifico)
    {
        $onlyInactive = $this->option('inactive') || $this->option('only-inactive');
        $onlyVendor = $this->option('vendor') || $this->option('vendedor');
        $onlyCredit = $this->option('credit');
        $estadoParam = $onlyInactive ? 'I' : null;

        $this->info('Iniciando sincronización de clientes' . ($onlyInactive ? ' (solo INACTIVOS)' : ($onlyVendor ? ' (SOLO VENDEDORES ASIGNADOS)' : ($onlyCredit ? ' (SOLO CRÉDITO)' : ''))) . '...');

        $clients = $contifico->fetchClients($estadoParam);

        if (isset($clients['results']) && is_array($clients['results'])) {
            $clients = $clients['results'];
        }

        if (empty($clients) || !is_array($clients)) {
            $this->error('No se obtuvieron clientes de Contifico.');
            return;
        }

        $count = 0;
        $processed = 0;
        $inactivated = 0;

        foreach ($clients as $cData) {
            $isCliente = filter_var($cData['es_cliente'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $nombre = $cData['razon_social'] ?? $cData['nombre_comercial'] ?? 'Sin Nombre';
            $tipoPersona = $cData['tipo'] ?? 'N'; // N = Natural, J = Jurídico

            $cedula = trim($cData['cedula'] ?? '');
            $ruc = trim($cData['ruc'] ?? '');

            // Coleccionar todas las posibles identificaciones asociadas a esta persona
            $possibleIdents = array_values(array_unique(array_filter([
                $ruc,
                $cedula,
                !empty($cedula) && strlen($cedula) === 10 ? $cedula . '001' : null,
                !empty($ruc) && strlen($ruc) === 13 ? substr($ruc, 0, 10) : null,
            ])));

            if (empty($possibleIdents)) continue;

            // Buscar TODOS los clientes locales que coincidan con contifico_id o cualquiera de las identificaciones
            $matchingClients = Client::where(function ($q) use ($cData, $possibleIdents) {
                if (!empty($cData['id'])) {
                    $q->where('contifico_id', $cData['id']);
                }
                foreach ($possibleIdents as $ident) {
                    $q->orWhere('identification', $ident);
                }
            })->get();

            // Si no es cliente en Contifico Y no existe localmente, lo omitimos
            if (!$isCliente && $matchingClients->isEmpty()) continue;

            $estado = $cData['estado'] ?? ($onlyInactive ? 'I' : 'A');
            $isActive = ($estado !== 'I') && !$onlyInactive;

            $creditLimit = null;
            if (array_key_exists('cupo_credito', $cData) && $cData['cupo_credito'] !== null && $cData['cupo_credito'] !== '') {
                $creditLimit = (float)$cData['cupo_credito'];
            }

            $creditDays = null;
            if (array_key_exists('dias_credito', $cData) && $cData['dias_credito'] !== null && $cData['dias_credito'] !== '') {
                $creditDays = (int)$cData['dias_credito'];
            }

            $vendedorData = $cData['vendedor_asignado'] ?? null;
            $vendedorId = is_array($vendedorData) ? ($vendedorData['id'] ?? null) : null;
            $vendedorNombre = is_array($vendedorData) ? ($vendedorData['nombre'] ?? null) : null;

            if ($matchingClients->isNotEmpty()) {
                if ($matchingClients->count() > 1) {
                    // Prefer target with exact RUC or latest created
                    $target = $matchingClients->firstWhere('identification', $ruc)
                        ?? $matchingClients->firstWhere('identification', $cData['id'])
                        ?? $matchingClients->last();
                    foreach ($matchingClients as $mClient) {
                        if ($mClient->id !== $target->id) {
                            $this->line(" - Auto-fusionando cliente duplicado: [{$mClient->identification}] -> [{$target->identification}]");
                            Client::mergeClients($mClient->id, $target->id);
                        }
                    }
                    $matchingClients = collect([$target->fresh()]);
                }

                // Actualizar el registro local (que ahora está unificado)
                foreach ($matchingClients as $client) {
                    if ($onlyVendor) {
                        $client->update([
                            'vendedor_id' => array_key_exists('vendedor_asignado', $cData) ? $vendedorId : $client->vendedor_id,
                            'vendedor_nombre' => array_key_exists('vendedor_asignado', $cData) ? $vendedorNombre : $client->vendedor_nombre,
                        ]);
                    } elseif ($onlyCredit) {
                        $client->update([
                            'credit_limit' => $creditLimit ?? $client->credit_limit,
                            'credit_days' => $creditDays ?? $client->credit_days,
                        ]);
                    } else {
                        $client->update([
                            'contifico_id' => $cData['id'] ?? $client->contifico_id,
                            'name' => $nombre,
                            'nombre_local' => $cData['nombre_comercial'] ?? $client->nombre_local,
                            'company_name' => $cData['nombre_comercial'] ?? $client->company_name,
                            'email' => !empty($cData['email']) ? $cData['email'] : $client->email,
                            'address' => !empty($cData['direccion']) ? $cData['direccion'] : $client->address,
                            'phone' => !empty($cData['telefonos']) ? $cData['telefonos'] : $client->phone,
                            'is_active' => $isActive,
                            'credit_limit' => $creditLimit ?? $client->credit_limit,
                            'credit_days' => $creditDays ?? $client->credit_days,
                            'vendedor_id' => array_key_exists('vendedor_asignado', $cData) ? $vendedorId : $client->vendedor_id,
                            'vendedor_nombre' => array_key_exists('vendedor_asignado', $cData) ? $vendedorNombre : $client->vendedor_nombre,
                        ]);
                    }
                }
            } else {
                $primaryIdent = !empty($ruc) ? $ruc : $cedula;
                $tipoIdentificacion = ($tipoPersona === 'J' || strlen($primaryIdent) === 13) ? 'RUC' : 'Cedula';

                $client = Client::create([
                    'identification' => $primaryIdent,
                    'name' => $nombre,
                    'contifico_id' => $cData['id'] ?? null,
                    'nombre_local' => $cData['nombre_comercial'] ?? null,
                    'company_name' => $cData['nombre_comercial'] ?? null,
                    'email' => $cData['email'] ?? null,
                    'address' => $cData['direccion'] ?? null,
                    'phone' => $cData['telefonos'] ?? null,
                    'identification_type' => $tipoIdentificacion,
                    'is_active' => $isActive,
                    'credit_limit' => $creditLimit,
                    'credit_days' => $creditDays,
                    'vendedor_id' => $vendedorId,
                    'vendedor_nombre' => $vendedorNombre,
                ]);
                $this->line(" - Nuevo Cliente: [{$client->identification}] {$client->name}");
                $count++;
            }

            if (!$isActive) {
                $inactivated++;
            }

            $processed++;
        }

        $this->info("Sincronización completada. Se procesaron {$processed} registros ({$inactivated} inactivos, {$count} nuevos).");
    }
}
