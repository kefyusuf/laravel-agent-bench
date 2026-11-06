<?php

namespace App\Jobs;

use App\Models\Digest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendDigestJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Digest $digest)
    {
    }

    public function handle(): void
    {
        $response = Http::asJson()
            ->withToken((string) config('services.ledgermail.key', 'test'))
            ->post('https://api.ledgermail.example/v1/digests', [
                'customer_id' => $this->digest->customer_id,
                'period' => now()->format('Y-m'),
            ]);

        $digestId = $response->json('digest_id');

        $this->digest->forceFill([
            'status' => Digest::STATUS_SENT,
            'provider_digest_id' => $digestId,
        ])->save();
    }
}
