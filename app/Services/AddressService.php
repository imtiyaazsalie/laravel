<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddressService
{
    public function search(string $address): array
    {
        try {
            $response = Http::baseUrl('https://api.mapbox.com')
                ->get('/search/geocode/v6/forward', [
                    'access_token' => config('services.mapbox.access_token'),
                    'q' => $address,
                    'proximity' => 'ip',
                    'types' => 'address',
                    'limit' => 5,
                ])
                ->throw()
                ->json();

            return $this->formatAddressResults($response['features'] ?? []);
        } catch (\Exception $e) {
            Log::error('Mapbox search error: '.$e->getMessage());

            return [];
        }
    }

    private function formatAddressResults(array $addresses): array
    {
        return array_map(function ($address) {
            $structuredAddress = collect($address['properties']['context'])
                ->mapWithKeys(function ($item, $key) {
                    return $key === 'address'
                        ? ['address_number' => $item['address_number']]
                        : [$key => $item['name']];
                })
                ->toArray();

            return [
                'full_address' => $address['properties']['full_address'],
                'coordinates' => [
                    'longitude' => $address['properties']['coordinates']['longitude'],
                    'latitude' => $address['properties']['coordinates']['latitude'],
                ],
                'structured_address' => $structuredAddress,
            ];
        }, $addresses);
    }
}
