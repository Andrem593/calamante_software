<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ContificoService;
use Illuminate\Console\Command;

class SyncContificoProducts extends Command
{
    protected $signature = 'sync:contifico-products';
    protected $description = 'Sincroniza productos desde Contifico';

    public function handle(ContificoService $contifico)
    {
        $this->info('Iniciando sincronización de categorías...');
        $categories = $contifico->fetchCategories();
        $catCount = 0;
        $categoryMap = [];

        foreach ($categories as $cData) {
            $category = \App\Models\Category::updateOrCreate(
                ['contifico_id' => $cData['id']],
                ['name' => $cData['nombre']]
            );
            $categoryMap[$cData['id']] = $category->id;
            if ($category->wasRecentlyCreated) $catCount++;
        }
        $this->info("Sincronización de categorías completada. Se crearon {$catCount} nuevas.");

        $this->info('Iniciando sincronización de productos...');
        
        \Log::info('Contifico Sync: Starting products fetch');
        $products = $contifico->fetchProducts();
        \Log::info('Contifico Sync: Products size: ' . (is_array($products) ? count($products) : 'not an array'));
        
        if (empty($products)) {
            $this->error('No se obtuvieron productos de Contifico.');
            \Log::warning('Contifico Sync: No products returned');
            return;
        }

        \Log::info('Contifico Sync: Processing ' . count($products) . ' products');

        $count = 0;
        foreach ($products as $pData) {
            if (empty($pData['codigo'])) {
                \Log::debug('Contifico Sync: Skipping product without code: ' . json_encode($pData));
                continue;
            }

            // Map fields based on API documentation:
            // codigo -> sku
            // nombre -> name
            // descripcion -> description
            // pvp1 -> price
            // cantidad_stock -> stock
            // estado -> status
            // porcentaje_iva -> tax_percentage
            
            $product = Product::updateOrCreate(
                ['sku' => $pData['codigo']],
                [
                    'contifico_id' => $pData['id'] ?? null,
                    'name' => $pData['nombre'] ?? $pData['descripcion'] ?? 'Sin nombre',
                    'description' => $pData['descripcion'] ?? null,
                    'price' => $pData['pvp1'] ?? 0.00,
                    'stock' => (int)($pData['cantidad_stock'] ?? 0),
                    'status' => $pData['estado'] ?? 'A',
                    'tax_percentage' => $pData['porcentaje_iva'] ?? 15.00,
                    'category_id' => isset($pData['categoria_id']) ? ($categoryMap[$pData['categoria_id']] ?? null) : null,
                    'is_visible' => ($pData['estado'] ?? 'A') === 'A',
                ]
            );

            if ($product->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info("Sincronización completada. Se crearon {$count} nuevos productos.");
    }
}
