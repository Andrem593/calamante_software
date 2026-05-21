<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Vendedor Test',
            'email' => 'vendedor@dados.com',
            'password' => bcrypt('password'),
        ]);

        \App\Models\Client::create([
            'name' => 'Cliente Demo',
            'email' => 'cliente@demo.com',
            'phone' => '123456789',
            'address' => 'Calle Falsa 123',
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);

        \App\Models\Product::create([
            'name' => 'Producto A',
            'price' => 100.00,
            'stock' => 50,
            'sku' => 'PROD-A',
        ]);

        \App\Models\Product::create([
            'name' => 'Producto B',
            'price' => 50.00,
            'stock' => 100,
            'sku' => 'PROD-B',
        ]);
    }
}
