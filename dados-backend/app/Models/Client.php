<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = [];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function specialPrices()
    {
        return $this->hasMany(SpecialPrice::class);
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

            // Eliminar cliente de origen
            $source->delete();
        });
    }
}
