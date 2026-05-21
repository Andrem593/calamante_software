<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\Order;
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
}
