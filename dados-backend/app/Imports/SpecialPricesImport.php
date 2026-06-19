<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Product;
use App\Models\SpecialPrice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SpecialPricesImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        // Omitir fila de cabeceras
        $isHeader = true;

        foreach ($rows as $row) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            // Mapeo por índices:
            // 0: IDENTIFICAC (Identificación del Cliente)
            // 1: Nombre Local (Nombre del Cliente)
            // 2: Precio (Precio Especial)
            // 3: PRODUCTO (Nombre del Producto)
            // 4: % Descuen (Porcentaje de Descuento)

            $identificacion = isset($row[0]) ? trim((string)$row[0]) : null;
            $nombreLocal = isset($row[1]) ? trim((string)$row[1]) : null;
            $precioRaw = isset($row[2]) ? trim((string)$row[2]) : null;
            $productoName = isset($row[3]) ? trim((string)$row[3]) : null;
            $descuentoRaw = isset($row[4]) ? trim((string)$row[4]) : null;

            if (empty($identificacion) || empty($productoName)) {
                continue;
            }

            // 1. Buscar o crear cliente
            $client = Client::where('identification', $identificacion)->first();
            if (!$client) {
                // Crear cliente con datos básicos si no existe
                $client = Client::create([
                    'name' => $nombreLocal ?: 'Cliente ' . $identificacion,
                    'nombre_local' => $nombreLocal,
                    'identification_type' => strlen($identificacion) === 13 ? 'RUC' : 'Cedula',
                    'identification' => $identificacion,
                ]);
            } else if ($nombreLocal && empty($client->nombre_local)) {
                $client->update(['nombre_local' => $nombreLocal]);
            }

            // 2. Buscar producto por nombre (insensible a mayúsculas/minúsculas)
            $product = Product::where('name', 'like', $productoName)->first();
            if (!$product) {
                $product = Product::whereRaw('LOWER(name) = ?', [strtolower(trim($productoName))])->first();
            }

            if (!$product) {
                \Log::warning("SpecialPricesImport: Producto no encontrado en BD: '{$productoName}' para cliente '{$identificacion}'. Fila omitida.");
                continue;
            }

            // 3. Procesar precio y descuento
            $precio = null;
            if ($precioRaw !== null && $precioRaw !== '') {
                $precioClean = str_replace(',', '.', $precioRaw);
                $precio = is_numeric($precioClean) ? (float)$precioClean : null;
            }

            $descuento = 0.00;
            if ($descuentoRaw !== null && $descuentoRaw !== '') {
                $descuentoClean = str_replace(',', '.', $descuentoRaw);
                $descuento = is_numeric($descuentoClean) ? (float)$descuentoClean : 0.00;
            }

            // 4. Guardar o actualizar
            SpecialPrice::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'product_id' => $product->id,
                ],
                [
                    'price' => $precio,
                    'discount_percentage' => $descuento,
                ]
            );
        }
    }
}
