<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;

class ShipmentService
{
    /**
     * Creates a shipment with the provider and records it.
     */
    public function createShipment(string $orderId, string $destination): Shipment
    {
        $response = Http::asJson()
            ->withToken((string) config('services.shipfast.key', 'test'))
            ->post('https://api.shipfast.example/v1/shipments', [
                'order_id' => $orderId,
                'destination' => $destination,
            ]);

        $response->throw();

        return Shipment::create([
            'order_id' => $orderId,
            'provider_reference' => (string) $response->json('reference'),
            'destination' => $destination,
        ]);
    }
}
