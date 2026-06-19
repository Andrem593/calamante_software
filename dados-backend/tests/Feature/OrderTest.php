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
}

