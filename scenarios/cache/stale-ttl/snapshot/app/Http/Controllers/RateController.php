<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;

class RateController extends Controller
{
    public function show(string $from, string $to, ExchangeRateService $rates): JsonResponse
    {
        return response()->json(['rate' => $rates->get($from, $to)]);
    }
}
