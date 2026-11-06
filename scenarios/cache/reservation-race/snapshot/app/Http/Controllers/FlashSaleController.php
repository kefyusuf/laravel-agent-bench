<?php

namespace App\Http\Controllers;

use App\Services\StockReservationService;
use Illuminate\Http\JsonResponse;

class FlashSaleController extends Controller
{
    public function reserve(string $sku, StockReservationService $reservations): JsonResponse
    {
        $granted = $reservations->reserve($sku);

        return response()->json(
            ['reserved' => $granted],
            $granted ? 200 : 410,
        );
    }
}
