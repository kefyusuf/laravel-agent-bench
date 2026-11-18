<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrderService
{
    public function createWithReservation(string $reference, string $destination): Order
    {
        return DB::transaction(function () use ($reference, $destination) {
            $order = Order::create([
                'reference' => $reference,
                'destination' => $destination,
                'status' => 'reserved',
            ]);

            $reservation = Http::asJson()
                ->withToken((string) config('services.dockreserve.key', 'test'))
                ->post('https://api.dockreserve.example/v1/reservations', [
                    'reference' => $reference,
                    'destination' => $destination,
                ]);

            if (! $reservation->json('slot_id')) {
                throw new \RuntimeException('dock reservation failed');
            }

            return $order;
        });
    }
}
