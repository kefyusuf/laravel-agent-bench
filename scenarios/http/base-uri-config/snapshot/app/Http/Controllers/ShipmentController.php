<?php

namespace App\Http\Controllers;

use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function store(Request $request, ShipmentService $shipments): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'destination' => ['required', 'string'],
        ]);

        $shipment = $shipments->createShipment($data['order_id'], $data['destination']);

        return response()->json([
            'shipment' => ['reference' => $shipment->provider_reference, 'destination' => $shipment->destination],
        ], 201);
    }
}
