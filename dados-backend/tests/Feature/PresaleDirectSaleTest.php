<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Product;
use App\Services\ContificoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PresaleDirectSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_invoices_retrieves_and_caches_by_day()
    {
        Setting::set('CONTIFICO_API_KEY', 'test_api_key_123');
        Cache::flush();

        // Fake Contifico endpoint
        Http::fake([
            'api.contifico.com/sistema/api/v1/registro/documento/*' => Http::response([
                [
                    'id' => 'contifico-inv-1',
                    'total' => 150.00,
                    'descripcion' => 'Venta en oficina',
                    'vendedor' => 'Oficina Central',
                    'fecha_emision' => '01/09/2026',
                    'estado' => 'P',
                    'anulado' => false,
                    'detalles' => []
                ]
            ], 200)
        ]);

        $service = new ContificoService();
        $invoices = $service->fetchInvoices('01/09/2026', '02/09/2026');

        $this->assertNotEmpty($invoices);
        $this->assertEquals('contifico-inv-1', $invoices[0]['id']);

        // Verify day 1 is cached
        $cacheKey = 'contifico_invoices_day_2026_09_01';
        $this->assertTrue(Cache::has($cacheKey));

        // Second call should read from cache without hitting Http again
        Http::fake([
            '*' => Http::response([], 500) // Should not be reached because it is cached
        ]);

        $cachedInvoices = $service->fetchInvoices('01/09/2026', '02/09/2026');
        $this->assertCount(1, $cachedInvoices);
        $this->assertEquals('contifico-inv-1', $cachedInvoices[0]['id']);
    }

    public function test_presale_direct_sale_endpoint_renders_with_contifico_row()
    {
        Setting::set('CONTIFICO_API_KEY', 'test_api_key_123');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com'
        ]);

        Http::fake([
            'api.contifico.com/sistema/api/v1/registro/documento/*' => Http::response([
                [
                    'id' => 'contifico-inv-direct',
                    'total' => 200.00,
                    'descripcion' => 'Venta directa Contifico',
                    'vendedor' => 'Mostrador',
                    'fecha_emision' => '01/09/2026',
                    'estado' => 'P',
                    'anulado' => false,
                    'detalles' => []
                ]
            ], 200)
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.presale-direct-sale', [
            'mode' => 'range',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-02'
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Admin/Reports/PresaleDirectSale')
                 ->has('reportData')
                 ->has('totals')
                 ->where('totals.total_amount', 200)
        );
    }

    public function test_presale_direct_sale_includes_zero_dollar_invoices_with_items()
    {
        Setting::set('CONTIFICO_API_KEY', 'test_api_key_123');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin2@test.com'
        ]);

        $product = Product::create([
            'name' => 'FUNDAS DE HIELO 2.5 KG',
            'price' => 1.00,
            'stock' => 100,
            'contifico_id' => 'prod-25kg'
        ]);

        Http::fake([
            'api.contifico.com/sistema/api/v1/registro/documento/*' => Http::response([
                [
                    'id' => 'contifico-inv-donation',
                    'total' => 0.00,
                    'descripcion' => 'DONACION COLEGIO',
                    'vendedor' => 'Mostrador',
                    'fecha_emision' => '01/09/2026',
                    'estado' => 'P',
                    'anulado' => false,
                    'detalles' => [
                        [
                            'producto_id' => 'prod-25kg',
                            'cantidad' => 25,
                            'precio' => 0.00
                        ]
                    ]
                ],
                [
                    'id' => 'contifico-inv-regular',
                    'total' => 100.00,
                    'descripcion' => 'Venta regular',
                    'vendedor' => 'Mostrador',
                    'fecha_emision' => '01/09/2026',
                    'estado' => 'P',
                    'anulado' => false,
                    'detalles' => [
                        [
                            'producto_id' => 'prod-25kg',
                            'cantidad' => 50,
                            'precio' => 2.00
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.presale-direct-sale', [
            'mode' => 'range',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-02'
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Admin/Reports/PresaleDirectSale')
                 ->where('totals.total_amount', 100)
                 ->where('totals.total_items', 75)
        );
    }
}
