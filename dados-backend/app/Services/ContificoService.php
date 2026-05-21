<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContificoService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api.contifico.com/sistema/api/v1/';
        $this->apiKey = Setting::get('CONTIFICO_API_KEY');
    }

    protected function getClient()
    {
        if (!$this->apiKey) {
            throw new \Exception('API Key de Contifico no configurada.');
        }

        return Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->timeout(30);
    }

    public function fetchClients()
    {
        try {
            $response = $this->getClient()->get($this->baseUrl . 'persona/');
            
            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Error fetching clients from Contifico: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching clients from Contifico: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchProducts()
    {
        try {
            $response = $this->getClient()->get($this->baseUrl . 'producto/');
            
            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Error fetching products from Contifico: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching products from Contifico: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchCategories()
    {
        try {
            $response = $this->getClient()->get($this->baseUrl . 'categoria/');
            
            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Error fetching categories from Contifico: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching categories from Contifico: ' . $e->getMessage());
            return [];
        }
    }

    public function createPreinvoice(\App\Models\Order $order)
    {
        $client = $order->client;
        if (!$client) {
            Log::warning("Order #{$order->id} cannot be pre-invoiced: Client not found.");
            return null;
        }

        $pos = Setting::get('CONTIFICO_POS_UUID');
        $branchName = $order->branch ? $order->branch->name : 'N/A';
        $branchRoute = $order->branch ? ($order->branch->route ?? 'N/A') : 'N/A';
        $userName = $order->user ? $order->user->name : 'Vendedor';
        $tipoPersona = $client->identification_type === 'Cedula' ? 'N' : 'J';

        $payload = [
            'pos' => $pos,
            'fecha_emision' => $order->created_at->format('d/m/Y'),
            'tipo_documento' => 'PRE',
            'estado' => 'P',
            'referencia' => $branchName,
            'cliente' => [
                'cedula' => $client->identification_type === 'Cedula' ? $client->identification : null,
                'ruc' => $client->identification_type === 'RUC' ? $client->identification : null,
                'razon_social' => $client->name,
                'tipo' => $tipoPersona,
                'es_cliente' => true,
                'direccion' => $client->address ?? 'N/A',
                'telefonos' => $client->phone ?? '',
                'email' => $client->email ?? 'notiene@mail.com'
            ],
            'descripcion' => "$userName - #{$order->id} - $branchRoute - $branchName",
            'subtotal_0' => 0.00,
            'subtotal_12' => 0.00,
            'iva' => 0.00,
            'total' => (float)$order->total,
            'detalles' => []
        ];

        $sub0 = 0;
        $sub12 = 0;
        $totalIva = 0;

        foreach ($order->items as $item) {
            $prod = $item->product;
            if (!$prod || !$prod->contifico_id) continue;

            $ivaPercent = (int)$prod->tax_percentage;
            $baseCero = 0;
            $baseGravable = 0;
            $taxAmount = 0;

            if ($ivaPercent > 0) {
                $baseGravable = (float)$item->subtotal;
                $taxAmount = round($baseGravable * ($ivaPercent / 100), 2);
                $sub12 += $baseGravable;
                $totalIva += $taxAmount;
            } else {
                $baseCero = (float)$item->subtotal;
                $sub0 += $baseCero;
            }

            $payload['detalles'][] = [
                'producto_id' => $prod->contifico_id,
                'cantidad' => (float)$item->quantity,
                'precio' => (float)$item->price,
                'porcentaje_iva' => $ivaPercent,
                'porcentaje_descuento' => 0.00,
                'base_cero' => round($baseCero, 2),
                'base_gravable' => round($baseGravable, 2),
                'base_no_gravable' => 0.00
            ];
        }

        $payload['subtotal_0'] = round($sub0, 2);
        $payload['subtotal_12'] = round($sub12, 2);
        $payload['iva'] = round($totalIva, 2);
        $finalTotal = round($sub0 + $sub12 + $totalIva, 2);
        $payload['total'] = $finalTotal;

        try {
            $response = $this->getClient()->post($this->baseUrl . 'documento/', $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                $order->update([
                    'contifico_id' => $data['id'] ?? null,
                    'is_preinvoiced' => true
                ]);

                // Segundo paso: Registrar el cobro si no es Crédito
                $this->registerPayment($order, $data['id'], $finalTotal);

                return $data;
            }

            Log::error("Error creating preinvoice for Order #{$order->id}: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Exception creating preinvoice for Order #{$order->id}: " . $e->getMessage());
            return null;
        }
    }

    protected function registerPayment($order, $contificoDocId, $amount)
    {
        $paymentMapping = [
            'Efectivo' => 'EF',
            'Transferencia' => 'TRA',
            'Cheque' => 'CQ', // Corregido de CH a CQ
        ];

        $formaCobro = $paymentMapping[$order->payment_method] ?? null;

        // Si es Crédito o no está en el mapa, no registramos cobro
        if (!$formaCobro) {
            return;
        }

        $payload = [
            'forma_cobro' => $formaCobro,
            'monto' => (float)$amount,
            'fecha' => $order->created_at->format('d/m/Y'),
        ];

        try {
            $response = $this->getClient()->post(
                $this->baseUrl . "documento/{$contificoDocId}/cobro/", 
                $payload
            );

            if (!$response->successful()) {
                Log::error("Error registering payment for Order #{$order->id} (Doc: $contificoDocId): " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Exception registering payment for Order #{$order->id}: " . $e->getMessage());
        }
    }
}
