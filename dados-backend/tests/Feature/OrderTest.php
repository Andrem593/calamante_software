<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\Order;
use App\Models\SpecialPrice;
use Laravel\Sanctum\Sanctum;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_successfully()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Test', 'email' => 'test@client.com']);
        $product = Product::create(['name' => 'Prod Test', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10.0,
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['total' => 20.0, 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_create_order_preserves_custom_created_at_from_offline_sync()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Offline', 'email' => 'offline@client.com']);
        $product = Product::create(['name' => 'Prod Offline', 'price' => 15.0, 'stock' => 10]);

        $customDate = '2026-09-28 14:30:00';

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 15.0,
                ]
            ],
            'created_at' => $customDate,
        ]);

        $response->assertStatus(201);
        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('2026-09-28 14:30:00', $order->created_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-29', $order->delivery_date->format('Y-m-d'));
        
        $item = $order->items()->first();
        $this->assertEquals('2026-09-28 14:30:00', $item->created_at->format('Y-m-d H:i:s'));

        // No debe enviarse a Contifico automáticamente por ser fecha anterior
        $this->assertNull($order->contifico_id);
        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order->id,
            'status' => 'pending_contifico_approval',
        ]);
    }

    public function test_create_order_with_discount_successfully()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Test 2', 'email' => 'test2@client.com']);
        $product = Product::create(['name' => 'Prod Test 2', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10.0,
                    'discount_percentage' => 10.00, // 10% discount -> subtotal = 18.0
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['total' => 18.0, 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'discount_percentage' => 10.00,
            'subtotal' => 18.0
        ]);
    }

    public function test_get_products_with_special_prices()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Test 3', 'email' => 'test3@client.com']);
        $product1 = Product::create(['name' => 'Prod Test A', 'price' => 10.0, 'stock' => 10, 'is_visible' => true, 'status' => 'A']);
        $product2 = Product::create(['name' => 'Prod Test B', 'price' => 20.0, 'stock' => 5, 'is_visible' => true, 'status' => 'A']);

        // Create special price for product1
        SpecialPrice::create([
            'client_id' => $client->id,
            'product_id' => $product1->id,
            'price' => 8.50,
            'discount_percentage' => 15.00
        ]);

        // Get products without client_id
        $responseNoClient = $this->getJson('/api/products');
        $responseNoClient->assertStatus(200);
        $dataNoClient = $responseNoClient->json();
        
        $prod1NoClient = collect($dataNoClient)->firstWhere('id', $product1->id);
        $prod2NoClient = collect($dataNoClient)->firstWhere('id', $product2->id);

        $this->assertEquals(10.0, $prod1NoClient['price']);
        $this->assertEquals(0.0, $prod1NoClient['discount_percentage']);
        $this->assertEquals(20.0, $prod2NoClient['price']);
        $this->assertEquals(0.0, $prod2NoClient['discount_percentage']);

        // Get products with client_id
        $responseWithClient = $this->getJson("/api/products?client_id={$client->id}");
        $responseWithClient->assertStatus(200);
        $dataWithClient = $responseWithClient->json();

        $prod1WithClient = collect($dataWithClient)->firstWhere('id', $product1->id);
        $prod2WithClient = collect($dataWithClient)->firstWhere('id', $product2->id);

        $this->assertEquals(8.5, $prod1WithClient['price']);
        $this->assertEquals(15.0, $prod1WithClient['discount_percentage']);
        $this->assertEquals(20.0, $prod2WithClient['price']);
        $this->assertEquals(0.0, $prod2WithClient['discount_percentage']);
    }

    public function test_get_products_for_atimasa_client_filters_only_allowed_products()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $atimasa = Client::create([
            'name' => 'ATIMASA',
            'identification' => '0991331859001',
            'email' => 'atimasa@test.com'
        ]);

        $normalClient = Client::create([
            'name' => 'Cliente Normal',
            'identification' => '1234567890001',
            'email' => 'normal@test.com'
        ]);

        $p1 = Product::create(['name' => 'FUNDAS DE HIELO LISTO 2.5 KG', 'price' => 0.60, 'stock' => 10, 'is_visible' => true, 'status' => 'A']);
        $p2 = Product::create(['name' => 'FUNDAS DE HIELO 3.5 KG', 'price' => 0.80, 'stock' => 10, 'is_visible' => true, 'status' => 'A']);
        $p3 = Product::create(['name' => 'VASOS 16ONZ', 'price' => 1.50, 'stock' => 10, 'is_visible' => true, 'status' => 'A']);

        // For ATIMASA, p1 and p3 should return
        $resAtimasa = $this->getJson("/api/products?client_id={$atimasa->id}");
        $resAtimasa->assertStatus(200);
        $dataAtimasa = $resAtimasa->json();
        $this->assertCount(2, $dataAtimasa);
        $names = array_column($dataAtimasa, 'name');
        $this->assertContains('FUNDAS DE HIELO LISTO 2.5 KG', $names);
        $this->assertContains('VASOS 16ONZ', $names);

        // For normal client, p1 (HIELO LISTO) must NOT return, only p2 and p3
        $resNormal = $this->getJson("/api/products?client_id={$normalClient->id}");
        $resNormal->assertStatus(200);
        $this->assertCount(2, $resNormal->json());
        $namesNormal = array_column($resNormal->json(), 'name');
        $this->assertNotContains('FUNDAS DE HIELO LISTO 2.5 KG', $namesNormal);
        $this->assertContains('FUNDAS DE HIELO 3.5 KG', $namesNormal);
        $this->assertContains('VASOS 16ONZ', $namesNormal);

        // For request without client_id, HIELO LISTO must also be excluded
        $resNoClient = $this->getJson('/api/products');
        $resNoClient->assertStatus(200);
        $namesNoClient = array_column($resNoClient->json(), 'name');
        $this->assertNotContains('FUNDAS DE HIELO LISTO 2.5 KG', $namesNoClient);
    }

    public function test_admin_sync_contifico_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Admin', 'email' => 'testadmin@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending'
        ]);

        // Mock ContificoService to return dummy preinvoice data
        $this->mock(\App\Services\ContificoService::class, function ($mock) use ($order) {
            $mock->shouldReceive('createPreinvoice')
                ->once()
                ->with(\Mockery::on(fn($o) => $o->id === $order->id))
                ->andReturn(['id' => 'contifico-preinvoice-123']);
        });

        $response = $this->post("/admin/orders/{$order->id}/sync-contifico");
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_admin_bulk_sync_contifico_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Admin Bulk', 'email' => 'testbulk@client.com']);
        $order1 = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 50.0,
            'status' => 'pending'
        ]);
        $order2 = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 75.0,
            'status' => 'pending'
        ]);

        $this->mock(\App\Services\ContificoService::class, function ($mock) {
            $mock->shouldReceive('createPreinvoice')
                ->twice()
                ->andReturn(['id' => 'contifico-preinvoice-123']);
        });

        $response = $this->post("/admin/orders/bulk-sync-contifico", [
            'order_ids' => [$order1->id, $order2->id]
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_create_direct_invoice_successfully()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Test 4', 'email' => 'test4@client.com']);
        $product = Product::create(['name' => 'Prod Test 4', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10.0,
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
            'credit_days' => 45,
            'is_direct_invoice' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'total' => 20.0,
            'credit_days' => 45,
            'is_invoiced' => true,
            'is_preinvoiced' => false,
        ]);
    }

    public function test_admin_can_cancel_order_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Cancel', 'email' => 'testcancel@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending'
        ]);

        $response = $this->post("/admin/orders/{$order->id}/cancel");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_sync_cancelled_order_with_contifico()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Cancel Sync', 'email' => 'testcancelsync@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'cancelled'
        ]);

        $response = $this->post("/admin/orders/{$order->id}/sync-contifico");
        $response->assertRedirect();
        $response->assertSessionHas('error', 'No se puede sincronizar un pedido anulado.');
    }

    public function test_create_order_with_four_decimal_promo_price_successfully()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Promo Test', 'email' => 'promo@client.com']);
        $product = Product::create(['name' => 'Prod Promo', 'price' => 0.0001, 'stock' => 10, 'is_visible' => true, 'status' => 'A']);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'price' => 0.0001,
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'total' => 0.0010,
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'price' => 0.0001,
            'subtotal' => 0.0010,
        ]);
    }

    public function test_admin_cannot_authorize_preinvoice_sri()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Admin 2', 'email' => 'testadmin2@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending',
            'is_invoiced' => false,
            'contifico_id' => 'some-contifico-id'
        ]);

        $response = $this->post("/admin/orders/{$order->id}/authorize-sri");
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Solo las facturas directas pueden ser autorizadas ante el SRI. Las pre-facturas no aplican para autorización electrónica.');
    }

    public function test_admin_authorize_sri_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Admin 3', 'email' => 'testadmin3@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending',
            'is_invoiced' => true,
            'contifico_id' => 'some-contifico-id'
        ]);

        // Mock ContificoService to return dummy SRI auth data
        $this->mock(\App\Services\ContificoService::class, function ($mock) {
            $mock->shouldReceive('authorizeInvoiceSri')
                ->once()
                ->with('some-contifico-id')
                ->andReturn(['status' => 'AUTORIZADO', 'autorizacion' => '1234567890']);
        });

        $response = $this->post("/admin/orders/{$order->id}/authorize-sri");
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Documento enviado a autorizar al SRI exitosamente.');
        
        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order->id,
            'status' => 'info',
        ]);
    }

    public function test_create_credit_order_without_direct_invoice_flag_is_preinvoiced()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Test 5', 'email' => 'test5@client.com']);
        $product = Product::create(['name' => 'Prod Test 5', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10.0,
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
            'payment_method' => 'Crédito',
            'credit_days' => 45,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'total' => 20.0,
            'credit_days' => 45,
            'is_invoiced' => false,
            'is_preinvoiced' => true,
        ]);
    }

    public function test_create_credit_order_with_direct_invoice_flag_is_invoiced()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Test 6', 'email' => 'test6@client.com']);
        $product = Product::create(['name' => 'Prod Test 6', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10.0,
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
            'payment_method' => 'Crédito',
            'credit_days' => 45,
            'is_direct_invoice' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'total' => 20.0,
            'credit_days' => 45,
            'is_invoiced' => true,
            'is_preinvoiced' => false,
        ]);
    }

    public function test_admin_can_view_order_details_with_contifico_status()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Admin 4', 'email' => 'testadmin4@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending',
            'is_invoiced' => true,
            'contifico_id' => 'some-contifico-id'
        ]);

        // Mock ContificoService to return dummy status
        $this->mock(\App\Services\ContificoService::class, function ($mock) {
            $mock->shouldReceive('getDocumentStatus')
                ->once()
                ->with('some-contifico-id')
                ->andReturn(['estado' => 'Autorizado']);
        });

        $response = $this->get("/admin/orders/{$order->id}");
        $response->assertStatus(200);
        
        // Assert Inertia page contains contifico_status => 'Autorizado'
        $response->assertViewHas('page', function ($page) {
            return $page['props']['contifico_status'] === 'Autorizado';
        });
    }

    public function test_admin_can_deliver_order_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Deliver', 'email' => 'testdeliver@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending'
        ]);

        $response = $this->post("/admin/orders/{$order->id}/deliver");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order->id,
            'status' => 'delivered',
        ]);
    }

    public function test_cannot_deliver_cancelled_order()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Deliver Cancelled', 'email' => 'testdelivercancelled@client.com']);
        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'cancelled'
        ]);

        $response = $this->post("/admin/orders/{$order->id}/deliver");
        $response->assertRedirect();
        $response->assertSessionHas('error', 'No se puede entregar un pedido anulado.');
    }

    public function test_admin_can_edit_preinvoice_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Edit', 'email' => 'testedit@client.com']);
        $product1 = Product::create(['name' => 'Prod Test 1', 'price' => 10.0, 'stock' => 10, 'tax_percentage' => 0]);
        $product2 = Product::create(['name' => 'Prod Test 2', 'price' => 20.0, 'stock' => 10, 'tax_percentage' => 0]);

        $order = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 10.0,
            'status' => 'pending',
            'is_invoiced' => false,
            'contifico_id' => 'existing-contifico-id'
        ]);

        $order->items()->create([
            'product_id' => $product1->id,
            'quantity' => 1,
            'price' => 10.0,
            'subtotal' => 10.0
        ]);

        // Mock ContificoService to verify recreation
        $this->mock(\App\Services\ContificoService::class, function ($mock) use ($order) {
            $mock->shouldReceive('createPreinvoice')
                ->once()
                ->with(\Mockery::on(fn($o) => $o->id === $order->id))
                ->andReturn(['id' => 'new-contifico-id']);
        });

        $response = $this->put("/admin/orders/{$order->id}", [
            'delivery_date' => '2026-06-12',
            'notes' => 'Updated notes',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 2, // 2 * 10.0 = 20.0
                    'price' => 10.0,
                    'discount_percentage' => 10.0 // 10% discount -> subtotal = 18.0
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 1, // 1 * 20.0 = 20.0
                    'price' => 20.0,
                    'discount_percentage' => 0.0 // subtotal = 20.0
                ]
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Total should be 18.0 + 20.0 = 38.0
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total' => 38.0,
            'notes' => 'Updated notes',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'discount_percentage' => 10.0,
            'subtotal' => 18.0
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'discount_percentage' => 0.0,
            'subtotal' => 20.0
        ]);

        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order->id,
            'status' => 'info',
            'details' => 'Pedido editado desde el panel de administración'
        ]);
    }

    public function test_delete_document_contifico()
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.contifico.com/sistema/api/v1/documento/*' => \Illuminate\Support\Facades\Http::response([], 200)
        ]);

        \App\Models\Setting::set('CONTIFICO_API_KEY', 'test-api-key');

        $contifico = new \App\Services\ContificoService();
        $result = $contifico->deleteDocument('some-doc-id');

        $this->assertTrue($result);
    }

    public function test_admin_can_bulk_deliver_orders_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Bulk 1', 'email' => 'bulk1@client.com']);
        $order1 = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending'
        ]);
        $order2 = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 200.0,
            'status' => 'pending'
        ]);
        $orderCancelled = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 300.0,
            'status' => 'cancelled'
        ]);

        $response = $this->post('/admin/orders/bulk-deliver', [
            'order_ids' => [$order1->id, $order2->id, $orderCancelled->id]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '2 pedidos marcados como entregados exitosamente.');

        $this->assertDatabaseHas('orders', ['id' => $order1->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', ['id' => $order2->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', ['id' => $orderCancelled->id, 'status' => 'cancelled']); // should remain cancelled

        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order1->id,
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('order_trackings', [
            'order_id' => $order2->id,
            'status' => 'delivered',
        ]);
    }

    public function test_admin_can_bulk_print_orders_successfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create(['name' => 'Cliente Test Bulk 2', 'email' => 'bulk2@client.com']);
        $order1 = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending'
        ]);
        $order2 = Order::create([
            'user_id' => $admin->id,
            'client_id' => $client->id,
            'total' => 200.0,
            'status' => 'pending'
        ]);

        $response = $this->get("/admin/orders/bulk-print?ids={$order1->id},{$order2->id}");
        
        $response->assertStatus(200);
        $response->assertViewHas('page', function ($page) use ($order1, $order2) {
            return $page['component'] === 'Admin/Orders/BulkPrint' &&
                   collect($page['props']['orders'])->contains('id', $order1->id) &&
                   collect($page['props']['orders'])->contains('id', $order2->id);
        });
    }

    public function test_create_cash_order_forces_credit_days_to_zero()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Cash Test', 'email' => 'cash@client.com']);
        $product = Product::create(['name' => 'Prod Cash', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 10.0,
                ]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
            'payment_method' => 'Efectivo',
            'credit_days' => 30, // even though request sends 30, it should be forced to 0
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'total' => 20.0,
            'payment_method' => 'Efectivo',
            'credit_days' => 0,
        ]);
    }

    public function test_create_order_payment_and_credit_days_depend_on_client()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Caso 1: Cliente con 20 días de crédito configurados en BD
        $creditClient = Client::create([
            'name' => 'Cliente Crédito 20d',
            'email' => 'cred20@client.com',
            'credit_days' => 20,
            'credit_limit' => 500,
        ]);
        $product = Product::create(['name' => 'Prod Test', 'price' => 10.0, 'stock' => 10]);

        // Intento de enviar Efectivo y 0 días para este cliente con crédito
        $res1 = $this->postJson('/api/orders', [
            'client_id' => $creditClient->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10.0]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
            'payment_method' => 'Efectivo',
            'credit_days' => 0,
        ]);
        $res1->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'client_id' => $creditClient->id,
            'payment_method' => 'Crédito',
            'credit_days' => 20,
        ]);

        // Caso 2: Cliente de contado (0 días de crédito y 0 límite)
        $cashClient = Client::create([
            'name' => 'Cliente Contado Estricto',
            'email' => 'cashstrict@client.com',
            'credit_days' => 0,
            'credit_limit' => 0,
        ]);

        // Intento de enviar Crédito y 60 días para este cliente de contado
        $res2 = $this->postJson('/api/orders', [
            'client_id' => $cashClient->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10.0]
            ],
            'latitude' => 10.0,
            'longitude' => -10.0,
            'payment_method' => 'Crédito',
            'credit_days' => 60,
        ]);
        $res2->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'client_id' => $cashClient->id,
            'payment_method' => 'Efectivo',
            'credit_days' => 0,
        ]);
    }

    public function test_merge_clients_successfully()
    {
        // 1. Create client A (Cedula, has branches, orders, special prices, email)
        $clientA = Client::create([
            'name' => 'Cliente A',
            'identification' => '1234567890',
            'identification_type' => 'Cedula',
            'email' => 'clienta@test.com',
        ]);
        
        $branch = \App\Models\Branch::create([
            'client_id' => $clientA->id,
            'name' => 'Sucursal Norte',
            'address' => 'Av. Norte 123',
        ]);

        $product = Product::create(['name' => 'Product X', 'price' => 10.0, 'stock' => 10]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $clientA->id,
            'total' => 50.0,
            'status' => 'pending',
        ]);

        $specialPrice = \App\Models\SpecialPrice::create([
            'client_id' => $clientA->id,
            'product_id' => $product->id,
            'price' => 8.0,
            'discount_percentage' => 20.0,
        ]);

        // 2. Create client B (RUC, no branches, no orders, no email)
        $clientB = Client::create([
            'name' => 'Cliente B',
            'identification' => '1234567890001',
            'identification_type' => 'RUC',
        ]);

        // 3. Merge A into B
        Client::mergeClients($clientA->id, $clientB->id);

        // 4. Assert client A is deleted
        $this->assertDatabaseMissing('clients', ['id' => $clientA->id]);

        // 5. Assert client B has the email of client A (preservation of email)
        $this->assertDatabaseHas('clients', [
            'id' => $clientB->id,
            'email' => 'clienta@test.com',
        ]);

        // 6. Assert branch points to B
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'client_id' => $clientB->id,
        ]);

        // 7. Assert order points to B
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'client_id' => $clientB->id,
        ]);

        // 8. Assert special price points to B
        $this->assertDatabaseHas('special_prices', [
            'id' => $specialPrice->id,
            'client_id' => $clientB->id,
        ]);
    }

    public function test_sync_contifico_clients_auto_merges_duplicates()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        // Client A has Cedula and contifico_id
        $clientA = Client::create([
            'name' => 'Cliente Duplicado',
            'identification' => '0987654321',
            'identification_type' => 'Cedula',
            'contifico_id' => 'contifico-xyz-123',
            'email' => 'old@email.com',
        ]);

        // Client B already exists with the RUC (perhaps from a manual entry or prior sync), but without contifico_id
        $clientB = Client::create([
            'name' => 'Cliente Duplicado',
            'identification' => '0987654321001',
            'identification_type' => 'RUC',
        ]);

        // Mock ContificoService to return the updated record (with RUC)
        $this->mock(\App\Services\ContificoService::class, function ($mock) {
            $mock->shouldReceive('fetchClients')
                ->once()
                ->andReturn([
                    [
                        'id' => 'contifico-xyz-123',
                        'razon_social' => 'Cliente Duplicado',
                        'tipo' => 'J', // Jurídico -> will use ruc
                        'ruc' => '0987654321001',
                        'email' => 'new@email.com',
                        'direccion' => 'Direccion Nueva',
                        'telefonos' => '123456',
                        'es_cliente' => true,
                    ]
                ]);
        });

        // Run sync
        $response = $this->post('/admin/clients/sync');
        $response->assertRedirect();

        // Client A should have been merged into B and deleted
        $this->assertDatabaseMissing('clients', ['id' => $clientA->id]);

        // Client B should remain, with contifico_id updated and email updated (if provided by API)
        $this->assertDatabaseHas('clients', [
            'id' => $clientB->id,
            'identification' => '0987654321001',
            'contifico_id' => 'contifico-xyz-123',
            'email' => 'new@email.com',
        ]);
    }

    public function test_inactive_client_hidden_from_api()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $activeClient = Client::create([
            'name' => 'Cliente Activo',
            'identification' => '1111111111001',
            'email' => 'active@client.com',
            'is_active' => true,
        ]);

        $inactiveClient = Client::create([
            'name' => 'Cliente Inactivo',
            'identification' => '2222222222001',
            'email' => 'inactive@client.com',
            'is_active' => false,
        ]);

        $resIndex = $this->getJson('/api/clients');
        $resIndex->assertStatus(200);
        $idsIndex = collect($resIndex->json())->pluck('id')->toArray();
        $this->assertContains($activeClient->id, $idsIndex);
        $this->assertNotContains($inactiveClient->id, $idsIndex);

        $resSearch = $this->getJson('/api/clients/search?q=Cliente');
        $resSearch->assertStatus(200);
        $idsSearch = collect($resSearch->json())->pluck('id')->toArray();
        $this->assertContains($activeClient->id, $idsSearch);
        $this->assertNotContains($inactiveClient->id, $idsSearch);
    }

    public function test_sync_clients_credit_limit_and_days()
    {
        $mockContifico = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico->method('fetchClients')->willReturn([
            'results' => [
                [
                    'id' => 'yBXdLwjPqtRJgbJZ',
                    'tipo' => 'J',
                    'es_cliente' => true,
                    'ruc' => '0991331859001',
                    'razon_social' => 'ATIMASA S.A',
                    'nombre_comercial' => 'ATIMASA S.A',
                    'email' => 'felectronicasp@primax.com.ec',
                    'dias_credito' => '30',
                    'cupo_credito' => '50000.00',
                ]
            ]
        ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico);

        $this->artisan('sync:contifico-clients')
            ->assertExitCode(0);

        $this->assertDatabaseHas('clients', [
            'identification' => '0991331859001',
            'credit_limit' => 50000.00,
            'credit_days' => 30,
        ]);
    }

    public function test_sync_clients_vendor_assigned()
    {
        $mockContifico = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico->method('fetchClients')->willReturn([
            'results' => [
                [
                    'id' => 'XKdwRYZ6xSGD4bgW',
                    'tipo' => 'N',
                    'es_cliente' => true,
                    'cedula' => '1204562324',
                    'razon_social' => 'CHALEN VELASCO JOSE ANTONIO',
                    'vendedor_asignado' => [
                        'id' => 'gDGe72jX2sAO0dn2',
                        'nombre' => 'Merchan Castañeda Ciro Enrique'
                    ]
                ]
            ]
        ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico);

        $this->artisan('sync:contifico-clients')
            ->assertExitCode(0);

        $this->assertDatabaseHas('clients', [
            'identification' => '1204562324',
            'vendedor_id' => 'gDGe72jX2sAO0dn2',
            'vendedor_nombre' => 'Merchan Castañeda Ciro Enrique',
        ]);
    }

    public function test_create_preinvoice_includes_vendedor_assigned()
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.contifico.com/sistema/api/v1/documento/' => \Illuminate\Support\Facades\Http::response(['id' => 'doc-123'], 201)
        ]);

        \App\Models\Setting::set('CONTIFICO_API_KEY', 'test-key');

        $client = Client::create([
            'name' => 'Cliente Con Vendedor',
            'identification' => '1204562324',
            'identification_type' => 'Cedula',
            'vendedor_id' => 'gDGe72jX2sAO0dn2',
            'vendedor_nombre' => 'Merchan Castañeda Ciro Enrique',
        ]);

        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'total' => 100.0,
            'status' => 'pending',
            'payment_method' => 'Efectivo',
            'is_invoiced' => false,
        ]);

        $product = Product::create([
            'name' => 'Producto Test',
            'price' => 100.0,
            'stock' => 10,
            'contifico_id' => 'prod-123',
            'tax_percentage' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100.0,
            'subtotal' => 100.0,
        ]);

        $contificoService = new \App\Services\ContificoService();
        $res = $contificoService->createPreinvoice($order);

        $this->assertNotNull($res);
        $this->assertEquals('doc-123', $res['id']);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            return isset($data['vendedor_id']) &&
                $data['vendedor_id'] === 'gDGe72jX2sAO0dn2';
        });
    }

    public function test_create_preinvoice_includes_nombre_local_and_notes_in_description()
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.contifico.com/sistema/api/v1/documento/' => \Illuminate\Support\Facades\Http::response(['id' => 'doc-456'], 201)
        ]);

        \App\Models\Setting::set('CONTIFICO_API_KEY', 'test-key');

        $client = Client::create([
            'name' => 'Cliente Con Local',
            'nombre_local' => 'Supermercado El Sol',
            'identification' => '0912345678',
            'identification_type' => 'Cedula',
        ]);

        $user = User::factory()->create(['name' => 'Vendedor Test']);

        $branch = \App\Models\Branch::create([
            'client_id' => $client->id,
            'name' => 'Sucursal Centro',
            'route' => 'Ruta 1',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'total' => 50.0,
            'status' => 'pending',
            'payment_method' => 'Efectivo',
            'notes' => 'Entregar por la tarde en bodega 2',
            'is_invoiced' => false,
        ]);

        $product = Product::create([
            'name' => 'Producto Test',
            'price' => 50.0,
            'stock' => 10,
            'contifico_id' => 'prod-456',
            'tax_percentage' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50.0,
            'subtotal' => 50.0,
        ]);

        $contificoService = new \App\Services\ContificoService();
        $res = $contificoService->createPreinvoice($order);

        $this->assertNotNull($res);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            $descripcion = $data['descripcion'] ?? '';
            return str_contains($descripcion, 'Local: Supermercado El Sol') &&
                   str_contains($descripcion, 'Sucursal: Sucursal Centro') &&
                   str_contains($descripcion, 'Obs: Entregar por la tarde en bodega 2');
        });
    }

    public function test_admin_can_sync_vendor_assigned_clients_via_route()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $mockContifico = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico->method('fetchClients')->willReturn([
            'results' => [
                [
                    'id' => 'XKdwRYZ6xSGD4bgW',
                    'tipo' => 'N',
                    'es_cliente' => true,
                    'cedula' => '1204562324',
                    'razon_social' => 'CHALEN VELASCO JOSE ANTONIO',
                    'vendedor_asignado' => [
                        'id' => 'gDGe72jX2sAO0dn2',
                        'nombre' => 'Merchan Castañeda Ciro Enrique'
                    ]
                ]
            ]
        ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico);

        $response = $this->post('/admin/clients/sync', ['vendedor' => true]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'identification' => '1204562324',
            'vendedor_id' => 'gDGe72jX2sAO0dn2',
            'vendedor_nombre' => 'Merchan Castañeda Ciro Enrique',
        ]);
    }

    public function test_sync_vendor_only_updates_vendor_fields_and_preserves_other_fields()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Client has custom local name and email
        $client = Client::create([
            'name' => 'Nombre Local Modificado',
            'identification' => '1204562324',
            'identification_type' => 'Cedula',
            'email' => 'mi-correo-custom@test.com',
            'vendedor_id' => null,
            'vendedor_nombre' => null,
        ]);

        $mockContifico = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico->method('fetchClients')->willReturn([
            'results' => [
                [
                    'id' => 'XKdwRYZ6xSGD4bgW',
                    'tipo' => 'N',
                    'es_cliente' => true,
                    'cedula' => '1204562324',
                    'razon_social' => 'NOMBRE DE CONTIFICO DIFERENTE',
                    'email' => 'contifico-email@test.com',
                    'vendedor_asignado' => [
                        'id' => 'gDGe72jX2sAO0dn2',
                        'nombre' => 'Merchan Castañeda Ciro Enrique'
                    ]
                ]
            ]
        ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico);

        $response = $this->post('/admin/clients/sync', ['vendedor' => true]);
        $response->assertRedirect();

        // Vendor should be updated, but name and email must be preserved!
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Nombre Local Modificado',
            'email' => 'mi-correo-custom@test.com',
            'vendedor_id' => 'gDGe72jX2sAO0dn2',
            'vendedor_nombre' => 'Merchan Castañeda Ciro Enrique',
        ]);
    }

    public function test_admin_can_manually_edit_client_status_and_vendor()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create([
            'name' => 'Cliente Para Editar',
            'identification' => '0990000000001',
            'identification_type' => 'RUC',
            'is_active' => true,
        ]);

        $response = $this->put("/admin/clients/{$client->id}", [
            'name' => 'Cliente Editado Manualmente',
            'identification_type' => 'RUC',
            'identification' => '0990000000001',
            'is_active' => false,
            'vendedor_id' => 'vendedor-custom-99',
            'vendedor_nombre' => 'Carlos Vendedor Manual',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Cliente Editado Manualmente',
            'is_active' => false,
            'vendedor_id' => 'vendedor-custom-99',
            'vendedor_nombre' => 'Carlos Vendedor Manual',
        ]);
    }

    public function test_resolve_contifico_persona_data_for_natural_person_with_ruc_and_company_ruc()
    {
        $contificoService = new \App\Services\ContificoService();

        // 1. Natural Person with RUC (e.g., 1801694140001 -> 3rd digit is 0)
        $naturalClient = new Client([
            'name' => 'MANTILLA FREIRE AMADA MARLEM',
            'identification' => '1801694140001',
            'identification_type' => 'RUC',
        ]);
        $resNatural = $contificoService->resolveContificoPersonaData($naturalClient);
        $this->assertEquals('N', $resNatural['tipo']);
        $this->assertEquals('1801694140', $resNatural['cedula']);
        $this->assertEquals('1801694140001', $resNatural['ruc']);

        // 2. Company / Juridical Person (e.g., 0991331859001 -> 3rd digit is 9)
        $companyClient = new Client([
            'name' => 'ATIMASA S.A.',
            'identification' => '0991331859001',
            'identification_type' => 'RUC',
        ]);
        $resCompany = $contificoService->resolveContificoPersonaData($companyClient);
        $this->assertEquals('J', $resCompany['tipo']);
        $this->assertNull($resCompany['cedula']);
        $this->assertEquals('0991331859001', $resCompany['ruc']);

        // 3. Natural Person with Cedula (10 digits)
        $cedulaClient = new Client([
            'name' => 'JUAN PEREZ',
            'identification' => '1801694140',
            'identification_type' => 'CEDULA',
        ]);
        $resCedula = $contificoService->resolveContificoPersonaData($cedulaClient);
        $this->assertEquals('N', $resCedula['tipo']);
        $this->assertEquals('1801694140', $resCedula['cedula']);
        $this->assertNull($resCedula['ruc']);
    }

    public function test_client_blocked_for_sales_cannot_place_order()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $blockedClient = Client::create([
            'name' => 'Cliente Bloqueado',
            'identification' => '1234567890001',
            'email' => 'blocked@client.com',
            'is_blocked' => true,
        ]);
        $product = Product::create(['name' => 'Prod Test Block', 'price' => 10.0, 'stock' => 10]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $blockedClient->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 10.0,
                ]
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['is_blocked' => true]);
        $this->assertDatabaseMissing('orders', ['client_id' => $blockedClient->id]);
    }

    public function test_admin_can_unblock_client_temporarily_for_days_allowing_orders()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $client = Client::create([
            'name' => 'Cliente Bloqueado Admin',
            'identification' => '0999999999001',
            'email' => 'blockedadmin@client.com',
            'is_blocked' => true,
        ]);

        // Unblock for 2 days
        $unblockResponse = $this->post("/admin/clients/{$client->id}/unblock", [
            'days' => 2,
            'reason' => 'Autorización gerencial',
        ]);

        $unblockResponse->assertRedirect();
        $unblockResponse->assertSessionHas('success');

        $client->refresh();
        $this->assertFalse($client->isBlockedForSales());
        $this->assertNotNull($client->unblocked_until);

        // Now order should succeed
        Sanctum::actingAs($admin, ['*']);
        $product = Product::create(['name' => 'Prod Test Unblock', 'price' => 15.0, 'stock' => 10]);

        $orderResponse = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 15.0,
                ]
            ],
        ]);

        $orderResponse->assertStatus(201);
        $this->assertDatabaseHas('orders', ['client_id' => $client->id, 'total' => 15.0]);
    }

    public function test_create_order_and_invoice_transmission_with_settings()
    {
        // 1. Configurar intervalos de sincronización en settings
        \App\Models\Setting::set('CONTIFICO_SYNC_INTERVAL', '12');
        \App\Models\Setting::set('CONTIFICO_API_KEY', 'test-api-key');

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create([
            'name' => 'Cliente Transmisión Test',
            'identification' => '0912345678001',
            'identification_type' => 'RUC',
            'email' => 'transmision@test.com',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Producto Transmisión',
            'price' => 25.0,
            'stock' => 50,
            'contifico_id' => 'prod-transmision-123',
            'tax_percentage' => 15.0,
        ]);

        // 2. Enviar pedido desde la app móvil al backend (POST /api/orders)
        $response = $this->postJson('/api/orders', [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 25.0,
                ]
            ],
            'notes' => 'Pedido enviado normalmente desde app móvil',
            'latitude' => -2.18,
            'longitude' => -79.88,
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'total' => 50.0,
            'payment_method' => 'Efectivo',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 25.0,
            'subtotal' => 50.0,
        ]);

        // 3. Fake HTTP de la API de Contifico para transmisión real de prefactura/factura
        \Illuminate\Support\Facades\Http::fake([
            'https://api.contifico.com/sistema/api/v1/documento/' => \Illuminate\Support\Facades\Http::response([
                'id' => 'doc-contifico-ok-999',
                'estado' => 'P',
                'total' => 50.0,
            ], 201),
        ]);

        // 4. Probar la sincronización con Contifico desde el backend admin
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $syncResponse = $this->post("/admin/orders/{$orderId}/sync-contifico");
        $syncResponse->assertRedirect();
        $syncResponse->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'contifico_id' => 'doc-contifico-ok-999',
            'is_preinvoiced' => true,
        ]);
    }

    public function test_prevent_duplicate_orders()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Dup Test', 'email' => 'dup@client.com']);
        $product = Product::create(['name' => 'Prod Dup', 'price' => 10.0, 'stock' => 100]);

        $payload = [
            'client_id' => $client->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'price' => 10.0,
                ]
            ],
            'latitude' => -2.18,
            'longitude' => -79.88,
        ];

        // Primer envío -> crea la orden #1
        $res1 = $this->postJson('/api/orders', $payload);
        $res1->assertStatus(201);
        $orderId1 = $res1->json('id');

        // Segundo envío idéntico inmediato -> detecta duplicado y retorna la orden #1
        $res2 = $this->postJson('/api/orders', $payload);
        $res2->assertStatus(200);
        $orderId2 = $res2->json('id');

        $this->assertEquals($orderId1, $orderId2);
        $this->assertEquals(1, Order::where('client_id', $client->id)->count());
    }

    public function test_prevent_duplicate_orders_different_branches_creates_separate_orders()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $client = Client::create(['name' => 'Cliente Multi Sucursal', 'email' => 'multi@client.com']);
        $branch1 = \App\Models\Branch::create(['client_id' => $client->id, 'name' => 'Sucursal Norte']);
        $branch2 = \App\Models\Branch::create(['client_id' => $client->id, 'name' => 'Sucursal Sur']);
        $product = Product::create(['name' => 'Prod Hielo', 'price' => 10.0, 'stock' => 100]);

        $payload1 = [
            'client_id' => $client->id,
            'branch_id' => $branch1->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'price' => 10.0,
                ]
            ],
            'latitude' => -2.18,
            'longitude' => -79.88,
        ];

        $payload2 = [
            'client_id' => $client->id,
            'branch_id' => $branch2->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'price' => 10.0,
                ]
            ],
            'latitude' => -2.18,
            'longitude' => -79.88,
        ];

        // Envío para Sucursal 1 -> crea orden 1
        $res1 = $this->postJson('/api/orders', $payload1);
        $res1->assertStatus(201);
        $orderId1 = $res1->json('id');

        // Envío para Sucursal 2 -> crea orden 2 (no debe considerarse duplicado)
        $res2 = $this->postJson('/api/orders', $payload2);
        $res2->assertStatus(201);
        $orderId2 = $res2->json('id');

        $this->assertNotEquals($orderId1, $orderId2);
        $this->assertEquals(2, Order::where('client_id', $client->id)->count());
    }

    public function test_cash_client_grace_period_for_overdue_invoices()
    {
        // 1. Cliente de contado con factura vencida hace 2 días (dentro del plazo de 3 días de gracia) -> NO debe estar bloqueado
        $cashClientWithinGrace = Client::create([
            'name' => 'Cliente Contado Gracia',
            'identification' => '0990000001001',
            'credit_days' => 0,
        ]);

        $mockContifico1 = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico1->expects($this->once())
            ->method('getPendingDocuments')
            ->with('0990000001001')
            ->willReturn([
                [
                    'id' => 'doc1',
                    'documento' => '001-001-000000001',
                    'fecha_vencimiento' => \Carbon\Carbon::today()->subDays(2)->format('d/m/Y'),
                    'total' => 100.0,
                    'saldo' => 100.0,
                ]
            ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico1);

        $this->assertFalse($cashClientWithinGrace->isBlockedForSales());

        // 2. Cliente de contado con factura vencida hace 4 días (fuera del plazo de 3 días de gracia) -> SI debe estar bloqueado
        $cashClientPastGrace = Client::create([
            'name' => 'Cliente Contado Bloqueado',
            'identification' => '0990000002001',
            'credit_days' => null,
        ]);

        $mockContifico2 = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico2->expects($this->once())
            ->method('getPendingDocuments')
            ->with('0990000002001')
            ->willReturn([
                [
                    'id' => 'doc2',
                    'documento' => '001-001-000000002',
                    'fecha_vencimiento' => \Carbon\Carbon::today()->subDays(4)->format('d/m/Y'),
                    'total' => 150.0,
                    'saldo' => 150.0,
                ]
            ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico2);

        $this->assertTrue($cashClientPastGrace->isBlockedForSales());

        // 3. Cliente de crédito con factura vencida hace 1 día -> SI debe estar bloqueado de inmediato
        $creditClient = Client::create([
            'name' => 'Cliente Credito Bloqueado',
            'identification' => '0990000003001',
            'credit_days' => 30,
        ]);

        $mockContifico3 = $this->createMock(\App\Services\ContificoService::class);
        $mockContifico3->expects($this->once())
            ->method('getPendingDocuments')
            ->with('0990000003001')
            ->willReturn([
                [
                    'id' => 'doc3',
                    'documento' => '001-001-000000003',
                    'fecha_vencimiento' => \Carbon\Carbon::today()->subDays(1)->format('d/m/Y'),
                    'total' => 200.0,
                    'saldo' => 200.0,
                ]
            ]);
        $this->app->instance(\App\Services\ContificoService::class, $mockContifico3);

        $this->assertTrue($creditClient->isBlockedForSales());
    }
}





