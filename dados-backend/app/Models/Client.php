<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = [];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'unblocked_until' => 'datetime',
    ];

    protected $appends = [
        'unblock_time_remaining',
    ];

    public function isBlockedForSales(): bool
    {
        // Si el cliente tiene un desbloqueo temporal activo, no está bloqueado
        if ($this->unblocked_until && \Carbon\Carbon::now()->lt($this->unblocked_until)) {
            return false;
        }

        // Si está marcado manualmente como bloqueado
        if ($this->is_blocked) {
            return true;
        }

        if (empty($this->identification)) {
            return false;
        }

        // Obtener la frecuencia configurada por el admin (1, 12 o 24 horas)
        $syncHours = (int) \App\Models\Setting::get('CONTIFICO_SYNC_INTERVAL', 12);
        if ($syncHours <= 0) {
            $syncHours = 12;
        }
        $cacheSeconds = $syncHours * 3600;

        // Clientes de contado (sin días de crédito o credit_days <= 0) tienen 3 días adicionales de plazo antes de bloquearse
        $isCashClient = empty($this->credit_days) || $this->credit_days <= 0;
        $graceDays = $isCashClient ? 3 : 0;

        $cacheKey = 'client_blocked_contifico_' . md5($this->identification . '_' . ($this->credit_days ?? 0));

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, $cacheSeconds, function () use ($graceDays) {
            try {
                /** @var \App\Services\ContificoService $contificoService */
                $contificoService = app(\App\Services\ContificoService::class);
                $rawDocs = $contificoService->getPendingDocuments($this->identification);
                $today = \Carbon\Carbon::today();

                if (is_array($rawDocs)) {
                    foreach ($rawDocs as $doc) {
                        $fechaVencimientoStr = $doc['fecha_vencimiento'] ?? null;
                        if ($fechaVencimientoStr) {
                            try {
                                $vencimiento = \Carbon\Carbon::createFromFormat('d/m/Y', $fechaVencimientoStr)->startOfDay();
                                // Para clientes de contado, se suman 3 días adicionales antes de considerarla motivo de bloqueo
                                if ($vencimiento->copy()->addDays($graceDays)->lt($today)) {
                                    return true;
                                }
                            } catch (\Exception $e) {
                                // Ignore date parse errors
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Error verificando facturas vencidas para cliente {$this->id}: " . $e->getMessage());
            }

            return false;
        });
    }

    public function getIsBlockedForSalesAttribute(): bool
    {
        return $this->isBlockedForSales();
    }

    public function getUnblockTimeRemainingAttribute(): ?string
    {
        if ($this->unblocked_until && \Carbon\Carbon::now()->lt($this->unblocked_until)) {
            return $this->unblocked_until->diffForHumans();
        }
        return null;
    }



    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function specialPrices()
    {
        return $this->hasMany(SpecialPrice::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public static function mergeClients($sourceId, $targetId)
    {
        if ($sourceId == $targetId) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($sourceId, $targetId) {
            $source = self::findOrFail($sourceId);
            $target = self::findOrFail($targetId);

            // Copiar el correo al destino si el destino no tiene correo y el origen sí
            if (empty($target->email) && !empty($source->email)) {
                $target->update(['email' => $source->email]);
            }

            // Mover sucursales (branches)
            Branch::where('client_id', $sourceId)->update(['client_id' => $targetId]);

            // Mover pedidos (orders)
            Order::where('client_id', $sourceId)->update(['client_id' => $targetId]);

            // Mover visitas (visits)
            Visit::where('client_id', $sourceId)->update(['client_id' => $targetId]);

            // Mover precios especiales (special prices), manejando duplicados
            foreach ($source->specialPrices as $price) {
                $exists = SpecialPrice::where('client_id', $targetId)
                    ->where('product_id', $price->product_id)
                    ->exists();
                if ($exists) {
                    $price->delete();
                } else {
                    $price->update(['client_id' => $targetId]);
                }
            }

            // Mover equipos del cliente
            $sourceEquipments = \Illuminate\Support\Facades\DB::table('client_equipment')->where('client_id', $sourceId)->get();
            foreach ($sourceEquipments as $eq) {
                $exists = \Illuminate\Support\Facades\DB::table('client_equipment')
                    ->where('client_id', $targetId)
                    ->where('branch_id', $eq->branch_id)
                    ->where('equipment_id', $eq->equipment_id)
                    ->exists();
                if (!$exists) {
                    \Illuminate\Support\Facades\DB::table('client_equipment')->insert([
                        'client_id' => $targetId,
                        'branch_id' => $eq->branch_id,
                        'equipment_id' => $eq->equipment_id
                    ]);
                }
            }

            // Eliminar cliente de origen
            $source->delete();
        });
    }

    public function equipments()
    {
        return $this->belongsToMany(Equipment::class, 'client_equipment')
            ->withPivot('branch_id');
    }
}
