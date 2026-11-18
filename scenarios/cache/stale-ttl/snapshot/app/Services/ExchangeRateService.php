<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    public function get(string $from, string $to): float
    {
        return (float) Cache::rememberForever(
            "rates:{$from}:{$to}",
            fn () => $this->fetchFromProvider($from, $to),
        );
    }

    private function fetchFromProvider(string $from, string $to): float
    {
        $response = Http::asJson()
            ->withToken((string) config('services.rates.key', 'test'))
            ->get("https://api.rates.example/v1/{$from}/{$to}");

        $response->throw();

        return (float) $response->json('rate');
    }
}
