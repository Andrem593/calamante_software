<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Client;
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

    public function fetchClients(?string $estado = null)
    {
        try {
            $allResults = [];
            $url = 'https://api.contifico.com/sistema/api/v2/persona/';
            if ($estado) {
                $url .= '?estado=' . urlencode($estado);
            }

            do {
                $response = $this->getClient()->get($url);

                if (!$response->successful()) {
                    Log::error('Error fetching clients from Contifico: ' . $response->body());
                    break;
                }

                $data = $response->json();

                if (isset($data['results']) && is_array($data['results'])) {
                    $allResults = array_merge($allResults, $data['results']);
                    $url = $data['next'] ?? null;
                } elseif (is_array($data)) {
                    $allResults = array_merge($allResults, $data);
                    $url = null;
                } else {
                    $url = null;
                }
            } while (!empty($url));

            return $allResults;
        } catch (\Exception $e) {
            Log::error('Exception fetching clients from Contifico: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchProducts(?string $estado = null)
    {
        try {
            $url = $this->baseUrl . 'producto/';
            if ($estado) {
                $url .= '?estado=' . urlencode($estado);
            }
            $response = $this->getClient()->get($url);
            
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
        if ($order->status === 'cancelled') {
            Log::warning("Order #{$order->id} cannot be pre-invoiced: The order has been cancelled.");
            return null;
        }

        // If it already has a contifico_id, delete the old one first before recreating
        if ($order->contifico_id) {
            try {
                $this->deleteDocument($order->contifico_id);
                $order->update(['contifico_id' => null]);
            } catch (\Exception $e) {
                Log::error("Failed to delete old Contifico document {$order->contifico_id} before recreation: " . $e->getMessage());
            }
        }

        $client = $order->client;
        if (!$client) {
            Log::warning("Order #{$order->id} cannot be pre-invoiced: Client not found.");
            return null;
        }

        $pos = Setting::get('CONTIFICO_POS_UUID');
        $branchName = $order->branch ? $order->branch->name : null;
        $branchRoute = $order->branch ? ($order->branch->route ?? 'N/A') : 'N/A';
        $userName = $order->user ? $order->user->name : 'Vendedor';
        $isDirect = !empty($order->is_invoiced) || (bool) Setting::get('CONTIFICO_DIRECT_INVOICING', false);
        $fechaEmision = \Carbon\Carbon::parse($order->created_at);
        $fechaVencimiento = $fechaEmision->copy()->addDays((int)($order->credit_days ?? 0));
        $vendedorAsignado = !empty($client->vendedor_nombre) ? $client->vendedor_nombre : null;
        $nombreLocal = !empty($client->nombre_local) ? $client->nombre_local : null;

        $descripcion = "Pedido #{$order->id}";
        if (!empty($nombreLocal)) {
            $descripcion .= " - Local: {$nombreLocal}";
        }
        if (!empty($branchName) && $branchName !== 'N/A') {
            $descripcion .= " - Sucursal: {$branchName}";
        }
        $descripcion .= " - Ruta: {$branchRoute} - Atendido por: {$userName}";
        if ($vendedorAsignado) {
            $descripcion .= " - Vendedor: {$vendedorAsignado}";
        }
        if (!empty(trim($order->notes ?? ''))) {
            $descripcion .= " - Obs: " . trim($order->notes);
        }

        $personaData = $this->resolveContificoPersonaData($client);
        $tipoPersona = !empty($personaData['tipo']) ? $personaData['tipo'] : 'N';

        $clientPayload = [
            'tipo' => $tipoPersona,
            'razon_social' => $client->name,
            'es_cliente' => true,
            'direccion' => !empty($client->address) ? $client->address : 'N/A',
            'telefonos' => $client->phone ?? '',
            'email' => !empty($client->email) ? $client->email : 'notiene@mail.com'
        ];
        if (!empty($personaData['cedula'])) {
            $clientPayload['cedula'] = $personaData['cedula'];
        }
        if (!empty($personaData['ruc'])) {
            $clientPayload['ruc'] = $personaData['ruc'];
        }

        $payload = [
            'pos' => $pos,
            'fecha_emision' => $fechaEmision->format('d/m/Y'),
            'fecha_vencimiento' => $fechaVencimiento->format('d/m/Y'),
            'tipo_documento' => $isDirect ? 'FAC' : 'PRE',
            'tipo_registro' => 'CLI',
            'estado' => 'P',
            'referencia' => $branchName ?? 'N/A',
            'cliente' => $clientPayload,
            'descripcion' => $descripcion,
            'subtotal_0' => 0.00,
            'subtotal_12' => 0.00,
            'iva' => 0.00,
            'total' => (float)$order->total,
            'cobros' => [],
            'detalles' => []
        ];

        if (!empty($client->vendedor_id)) {
            $payload['vendedor_id'] = $client->vendedor_id;
        }

        if ($isDirect) {
            $invoiceDetails = $this->getLastInvoiceDetails();
            $payload['documento'] = $invoiceDetails['numero'];
            $payload['autorizacion'] = "";
            $payload['electronico'] = true;
        }

        $sub0 = 0;
        $sub12 = 0;
        $totalIva = 0;

        foreach ($order->items as $item) {
            $prod = $item->product;
            if (!$prod || !$prod->contifico_id) continue;

            $ivaPercent = (int)$prod->tax_percentage;
            $baseCero = 0;
            $baseGravable = 0;

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
                'porcentaje_descuento' => (float)($item->discount_percentage ?? 0.00),
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

        // En la API V1, no se deben enviar cobros automáticos en el payload inicial para evitar duplicidades
        // (el efectivo se omite y el crédito 'OT' no es soportado por el servicio de cobros).
        $payload['cobros'] = [];

        try {
            Log::info("Enviando documento a Contifico para Pedido #{$order->id}: " . json_encode($payload));
            $response = $this->getClient()->post($this->baseUrl . 'documento/', $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                 $order->update([
                    'contifico_id' => $data['id'] ?? null,
                    'is_invoiced' => $order->is_invoiced,
                    'is_preinvoiced' => !$order->is_invoiced
                ]);

                // Segundo paso: Registrar el cobro
                $this->registerPayment($order, $data['id'], $finalTotal);

                // Si es una factura (FAC) directa, la enviamos de inmediato a autorizar al SRI
                if ($isDirect) {
                    $this->authorizeInvoiceSri($data['id']);
                }

                return $data;
            }

            $errorMsg = "Error de Contifico (Código {$response->status()}): " . $response->body();
            Log::error("Error enviando documento a Contifico para Pedido #{$order->id}: {$errorMsg} | Payload enviado: " . json_encode($payload));
            throw new \Exception($errorMsg);
        } catch (\Exception $e) {
            Log::error("Exception creando prefactura para Pedido #{$order->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function getLastInvoiceDetails()
    {
        $pos = Setting::get('CONTIFICO_POS_UUID');
        
        // 1. Intentar obtener el último número de factura (FAC) de este POS (limitado a 5 resultados para optimización de velocidad)
        $url = $this->baseUrl . 'registro/documento/?tipo_registro=CLI&tipo=FAC&result_size=5';
        if ($pos) {
            $url .= '&pos=' . urlencode($pos);
        }

        try {
            $response = $this->getClient()->get($url);
            if ($response->successful()) {
                $documents = $response->json();
                if (is_array($documents) && !empty($documents)) {
                    $maxNum = -1;
                    $prefix = null;
                    $lastAutorizacion = null;
                    foreach ($documents as $doc) {
                        $numStr = $doc['documento'] ?? $doc['numero'] ?? null;
                        if ($numStr && preg_match('/^(\d{3}-\d{3})-(\d{9})$/', $numStr, $matches)) {
                            $seq = (int)$matches[2];
                            if ($seq > $maxNum) {
                                $maxNum = $seq;
                                $prefix = $matches[1];
                                $lastAutorizacion = $doc['autorizacion'] ?? $doc['numero_autorizacion'] ?? null;
                            }
                        }
                    }
                    if ($prefix) {
                        $nextSeq = $maxNum + 1;
                        return [
                            'numero' => $prefix . '-' . str_pad($nextSeq, 9, '0', STR_PAD_LEFT),
                            'autorizacion' => $lastAutorizacion
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error fetching last invoice number from Contifico: " . $e->getMessage());
        }

        // 2. Si no hay facturas, intentar obtener prefacturas (PRE) de este POS para descubrir el prefijo (limitado a 5)
        $urlPre = $this->baseUrl . 'registro/documento/?tipo_registro=CLI&tipo=PRE&result_size=5';
        if ($pos) {
            $urlPre .= '&pos=' . urlencode($pos);
        }

        try {
            $responsePre = $this->getClient()->get($urlPre);
            if ($responsePre->successful()) {
                $documentsPre = $responsePre->json();
                if (is_array($documentsPre) && !empty($documentsPre)) {
                    foreach ($documentsPre as $doc) {
                        $numStr = $doc['documento'] ?? $doc['numero'] ?? null;
                        if ($numStr && preg_match('/^(\d{3}-\d{3})-\d{9}$/', $numStr, $matches)) {
                            return [
                                'numero' => $matches[1] . '-000000001',
                                'autorizacion' => $doc['autorizacion'] ?? $doc['numero_autorizacion'] ?? null
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error fetching pre-invoices to discover prefix: " . $e->getMessage());
        }

        // 3. Fallback final si no se encuentra nada
        return [
            'numero' => '001-001-000000001',
            'autorizacion' => null
        ];
    }

    protected function registerPayment($order, $contificoDocId, $amount)
    {
        $methodLower = strtolower($order->payment_method ?? '');

        if (str_contains($methodLower, 'efec')) {
            // NO ENVIAR RECAUDACIONES DE EFECTIVO CON CAJA
            return;
        } elseif (str_contains($methodLower, 'cred') || str_contains($methodLower, 'créd')) {
            // Para crédito, ya se envió en el payload inicial del documento como 'OT'
            return;
        } elseif (str_contains($methodLower, 'trans') || str_contains($methodLower, 'depo')) {
            $formaCobro = 'TRA';
        } elseif (str_contains($methodLower, 'cheq')) {
            $formaCobro = 'CQ';
        } else {
            $formaCobro = null;
        }

        if (!$formaCobro) {
            return;
        }

        $payload = [
            'forma_cobro' => $formaCobro,
            'monto' => (float)$amount,
            'fecha' => $order->created_at->format('d/m/Y'),
        ];

        if ($formaCobro === 'OT') {
            $payload['plazo'] = (int)($order->credit_days ?? 0);
            $payload['unidad'] = 'd';
        }

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

    public function authorizeInvoiceSri($contificoDocId)
    {
        try {
            $response = $this->getClient()->put(
                $this->baseUrl . "documento/{$contificoDocId}/sri/"
            );

            if ($response->successful()) {
                return $response->json();
            }

            $errorMsg = "Error de Contifico (Código {$response->status()}): " . $response->body();
            Log::error("Error authorizing document $contificoDocId at SRI: " . $errorMsg);
            throw new \Exception($errorMsg);
        } catch (\Exception $e) {
            Log::error("Exception authorizing document $contificoDocId at SRI: " . $e->getMessage());
            throw $e;
        }
    }

    public function getDocumentStatus($contificoDocId)
    {
        try {
            $response = $this->getClient()->get(
                $this->baseUrl . "documento/{$contificoDocId}/estado/"
            );

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Error fetching status for document $contificoDocId: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Exception fetching status for document $contificoDocId: " . $e->getMessage());
            return null;
        }
    }

    public function deleteDocument($contificoDocId)
    {
        try {
            $response = $this->getClient()->delete($this->baseUrl . "documento/{$contificoDocId}/");
            if ($response->successful()) {
                return true;
            }
            Log::error("Error deleting document $contificoDocId in Contifico: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Exception deleting document $contificoDocId in Contifico: " . $e->getMessage());
            return false;
        }
    }

    public function getDocument($contificoDocId)
    {
        try {
            $response = $this->getClient()->get($this->baseUrl . "documento/{$contificoDocId}/");
            if ($response->successful()) {
                return $response->json();
            }
            Log::error("Error fetching document $contificoDocId from Contifico: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Exception fetching document $contificoDocId from Contifico: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene los documentos pendientes de pago de un cliente por su RUC o Cédula.
     */
    public function getPendingDocuments(string $identification)
    {
        try {
            $url = $this->baseUrl . 'registro/documento/?persona_identificacion=' . urlencode($identification) . '&tipo=FAC&tipo_registro=CLI&estado=P';
            $response = $this->getClient()->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Error fetching pending documents for $identification from Contifico: " . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error("Exception fetching pending documents for $identification from Contifico: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las facturas de venta emitidas en Contifico dentro de un rango de fechas.
     */
    public function fetchInvoices(?string $from = null, ?string $to = null)
    {
        try {
            $url = $this->baseUrl . 'registro/documento/?tipo_registro=CLI&tipo=FAC';
            if ($from) {
                $url .= '&fecha_inicio=' . urlencode($from);
            }
            $response = $this->getClient()->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Error fetching invoices from Contifico: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching invoices from Contifico: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resuelve los datos de la persona (tipo: N/J/I, cedula, ruc) para Contifico según estándar del SRI de Ecuador.
     */
    public function resolveContificoPersonaData(Client $client): array
    {
        $ident = trim($client->identification ?? '');
        $cleanIdent = preg_replace('/[^0-9]/', '', $ident);
        $identType = mb_strtolower(trim($client->identification_type ?? ''));

        if ($cleanIdent === '9999999999' || $cleanIdent === '9999999999999' || str_contains($identType, 'consumidor') || str_contains($identType, 'sin')) {
            return [
                'tipo' => 'I',
                'cedula' => null,
                'ruc' => null,
            ];
        }

        if (strlen($cleanIdent) === 13) {
            $thirdDigit = (int)$cleanIdent[2];
            if ($thirdDigit < 6) {
                // Persona Natural con RUC (3er dígito 0-5) -> tipo 'N'
                return [
                    'tipo' => 'N',
                    'cedula' => substr($cleanIdent, 0, 10),
                    'ruc' => $cleanIdent,
                ];
            } else {
                // Persona Jurídica / Sociedad (3er dígito 6, 9) -> tipo 'J'
                return [
                    'tipo' => 'J',
                    'cedula' => null,
                    'ruc' => $cleanIdent,
                ];
            }
        }

        if (strlen($cleanIdent) === 10) {
            // Cédula de Persona Natural -> tipo 'N'
            return [
                'tipo' => 'N',
                'cedula' => $cleanIdent,
                'ruc' => null,
            ];
        }

        $isCedula = str_contains($identType, 'ced') || str_contains($identType, 'céd') || str_contains($identType, 'pasaporte');
        return [
            'tipo' => $isCedula ? 'N' : 'J',
            'cedula' => $isCedula ? $ident : null,
            'ruc' => !$isCedula ? $ident : null,
        ];
    }
}


