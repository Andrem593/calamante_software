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
    ];


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
