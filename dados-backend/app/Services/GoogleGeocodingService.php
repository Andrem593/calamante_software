<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GoogleGeocodingService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = Setting::get('GOOGLE_MAPS_API_KEY') 
            ?: env('GOOGLE_MAPS_API_KEY')
            ?: config('services.google.maps_key');
    }

    /**
     * Resuelve el sector (barrio/sublocalidad) y parroquia (cantón/localidad) para un par de coordenadas.
     */
    public function getSectorAndParroquia(?float $lat, ?float $lng): array
    {
        if (empty($lat) || empty($lng)) {
            return [
                'sector' => 'Sin coordenadas',
                'parroquia' => 'Sin coordenadas',
                'formatted_address' => 'N/A',
                'status' => 'MISSING_COORDINATES'
            ];
        }

        if (empty($this->apiKey)) {
            return [
                'sector' => 'API Key requerida',
                'parroquia' => 'API Key requerida',
                'formatted_address' => 'N/A',
                'status' => 'NO_API_KEY'
            ];
        }

        // Redondear lat y lng a 5 decimales (~1 metro) para el caché
        $roundedLat = round($lat, 5);
        $roundedLng = round($lng, 5);
        $cacheKey = "google_geocode_{$roundedLat}_{$roundedLng}";

        // Verificar si existe en caché (solo guardamos en caché respuestas válidas 'OK')
        try {
            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && ($cached['status'] ?? '') === 'OK') {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Cache check failed in GoogleGeocodingService: " . $e->getMessage());
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$roundedLat},{$roundedLng}",
                'key' => $this->apiKey,
                'language' => 'es'
            ]);

            if (!$response->successful()) {
                Log::error("Error calling Google Geocoding API: " . $response->body());
                return [
                    'sector' => 'Error de API',
                    'parroquia' => 'Error de API',
                    'formatted_address' => 'N/A',
                    'status' => 'HTTP_ERROR'
                ];
            }

            $data = $response->json();
            $status = $data['status'] ?? 'UNKNOWN';

            if ($status === 'REQUEST_DENIED') {
                $errorMsg = $data['error_message'] ?? '';
                Log::warning("Google Geocoding REQUEST_DENIED: {$errorMsg}");
                return [
                    'sector' => 'Activar Geocoding API en Google Cloud',
                    'parroquia' => 'Activar Geocoding API en Google Cloud',
                    'formatted_address' => 'Es necesario activar Geocoding API en Google Cloud Console',
                    'status' => 'REQUEST_DENIED'
                ];
            }

            if ($status === 'OVER_QUERY_LIMIT') {
                return [
                    'sector' => 'Límite de cuota Google excedido',
                    'parroquia' => 'Límite de cuota Google excedido',
                    'formatted_address' => 'N/A',
                    'status' => 'OVER_QUERY_LIMIT'
                ];
            }

            if ($status !== 'OK' || empty($data['results'])) {
                Log::warning("Google Geocoding non-OK status [{$status}] for coordinates ({$roundedLat}, {$roundedLng})");
                return [
                    'sector' => 'Sin resultados',
                    'parroquia' => 'Sin resultados',
                    'formatted_address' => 'N/A',
                    'status' => $status
                ];
            }

            $firstResult = $data['results'][0];
            $components = $firstResult['address_components'] ?? [];
            $formattedAddress = $firstResult['formatted_address'] ?? 'N/A';

            $sector = null;
            $parroquia = null;
            $canton = null;

            foreach ($components as $component) {
                $types = $component['types'] ?? [];
                $longName = $component['long_name'] ?? '';

                // Detectar Sector / Barrio
                if (empty($sector)) {
                    if (in_array('neighborhood', $types) || in_array('sublocality_level_1', $types) || in_array('sublocality', $types)) {
                        $sector = $longName;
                    }
                }

                // Detectar Parroquia
                if (empty($parroquia)) {
                    if (in_array('administrative_area_level_3', $types) || in_array('sublocality_level_2', $types)) {
                        $parroquia = $longName;
                    }
                }

                // Detectar Cantón / Ciudad / Locality como fallback para Parroquia
                if (empty($canton)) {
                    if (in_array('locality', $types) || in_array('administrative_area_level_2', $types)) {
                        $canton = $longName;
                    }
                }
            }

            // Fallback de sector si no se halló barrio
            if (empty($sector)) {
                foreach ($components as $component) {
                    $types = $component['types'] ?? [];
                    if (in_array('route', $types) || in_array('point_of_interest', $types)) {
                        $sector = $component['long_name'];
                        break;
                    }
                }
            }

            $result = [
                'sector' => $sector ?? $canton ?? 'Sin especificar',
                'parroquia' => $parroquia ?? $canton ?? 'Sin especificar',
                'formatted_address' => $formattedAddress,
                'status' => 'OK'
            ];

            // Solo guardar en caché si la respuesta fue exitosa OK
            try {
                Cache::put($cacheKey, $result, now()->addDays(30));
            } catch (\Throwable $e) {
                // Ignore cache put errors
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Exception in GoogleGeocodingService: " . $e->getMessage());
            return [
                'sector' => 'Error de conexión',
                'parroquia' => 'Error de conexión',
                'formatted_address' => 'N/A',
                'status' => 'EXCEPTION'
            ];
        }
    }
}
