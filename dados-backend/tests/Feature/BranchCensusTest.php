<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchCensusTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_new_branch_via_census()
    {
        $client = Client::create([
            'name' => 'Cliente Test Censo',
            'identification' => '0999999999001',
            'email' => 'cliente@test.com',
            'address' => 'Guayaquil',
            'phone' => '0999999999',
        ]);

        $payload = [
            'client_id' => $client->id,
            'name' => 'Sucursal Norte - Alborada',
            'code' => 'SUC-001',
            'address' => 'Av. Rodolfo Baquerizo Nazur',
            'sector' => 'Alborada',
            'parroquia' => 'Tarqui',
            'phone' => '0987654321',
            'email' => 'alborada@test.com',
            'latitude' => -2.1456789,
            'longitude' => -79.8912345,
        ];

        $response = $this->postJson("/api/clients/{$client->id}/branches/census", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'branch' => [
                    'client_id' => $client->id,
                    'name' => 'Sucursal Norte - Alborada',
                    'sector' => 'Alborada',
                    'latitude' => -2.1456789,
                    'longitude' => -79.8912345,
                ]
            ]);

        $this->assertDatabaseHas('branches', [
            'client_id' => $client->id,
            'name' => 'Sucursal Norte - Alborada',
            'sector' => 'Alborada',
            'latitude' => -2.1456789,
            'longitude' => -79.8912345,
        ]);
    }

    public function test_can_update_existing_branch_via_census_without_overwriting_existing_data()
    {
        $client = Client::create([
            'name' => 'Cliente Existente',
            'email' => 'existente@test.com',
        ]);
        $branch = Branch::create([
            'client_id' => $client->id,
            'name' => 'Matriz Centro Histórico',
            'code' => 'MAT-01',
            'address' => 'Av. 9 de Octubre y Pichincha',
            'phone' => '042000000',
            'email' => 'matriz@test.com',
        ]);

        // Censo actualiza coordenadas y sector pero no provee name ni phone ni email (deben preservarse)
        $payload = [
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'sector' => 'Centro',
            'latitude' => -2.189445,
            'longitude' => -79.882112,
        ];

        $response = $this->postJson("/api/branches/census", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $branch->refresh();
        $this->assertEquals('Matriz Centro Histórico', $branch->name);
        $this->assertEquals('042000000', $branch->phone);
        $this->assertEquals('matriz@test.com', $branch->email);
        $this->assertEquals('Av. 9 de Octubre y Pichincha', $branch->address);
        $this->assertEquals('Centro', $branch->sector);
        $this->assertEquals(-2.189445, (float)$branch->latitude);
        $this->assertEquals(-79.882112, (float)$branch->longitude);
        $this->assertNotNull($branch->geocoded_at);
    }

    public function test_branches_endpoint_returns_geolocation_fields()
    {
        $client = Client::create([
            'name' => 'Cliente Geo',
            'email' => 'geo@test.com',
        ]);
        Branch::create([
            'client_id' => $client->id,
            'name' => 'Sucursal Georreferenciada',
            'address' => 'Vía a la Costa',
            'sector' => 'Vía Costa',
            'latitude' => -2.180000,
            'longitude' => -80.010000,
            'geocoded_at' => now(),
        ]);

        $response = $this->getJson("/api/clients/{$client->id}/branches");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals(-2.180000, (float)$data[0]['latitude']);
        $this->assertEquals(-80.010000, (float)$data[0]['longitude']);
        $this->assertEquals('Vía Costa', $data[0]['sector']);
        $this->assertNotNull($data[0]['geocoded_at']);
    }
}
