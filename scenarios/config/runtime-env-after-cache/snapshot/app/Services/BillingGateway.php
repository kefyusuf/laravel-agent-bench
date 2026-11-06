<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BillingGateway
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('PAYRAIL_API_KEY', 'sandbox-key');
    }

    /**
     * Charges the customer and returns the provider reference.
     *
     * @throws ConnectionException
     */
    public function charge(int $customerId, int $amountCents): string
    {
        $response = Http::asJson()
            ->withToken($this->apiKey)
            ->post('https://api.billinghub.example/v1/charges', [
                'customer_id' => $customerId,
                'amount_cents' => $amountCents,
            ]);

        return (string) $response->json('reference');
    }
}
