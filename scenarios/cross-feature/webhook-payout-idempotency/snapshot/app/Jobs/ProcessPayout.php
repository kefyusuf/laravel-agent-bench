<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProcessPayout implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $orderId)
    {
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $lock = Cache::lock("payout:order:{$this->orderId}", 30);

        try {
            $lock->block(5);

            $order = Order::findOrFail($this->orderId);

            if (Payout::where('order_id', $order->id)->exists()) {
                return;
            }

            $response = Http::asJson()
                ->withToken((string) config('services.payrail.api_token'))
                ->post('https://api.payrail.example/v1/payouts', [
                    'order_id' => $order->id,
                    'amount_cents' => $order->total_cents,
                ]);

            $response->throw();

            Payout::create([
                'order_id' => $order->id,
                'amount_cents' => $order->total_cents,
                'provider_reference' => $response->json('reference'),
            ]);
        } finally {
            $lock->release();
        }
    }
}
