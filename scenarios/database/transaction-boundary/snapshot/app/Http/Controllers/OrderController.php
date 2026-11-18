<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'destination' => ['required', 'string', 'max:255'],
        ]);

        $order = $orders->createWithReservation($data['reference'], $data['destination']);

        return response()->json([
            'order' => ['reference' => $order->reference, 'status' => $order->status],
        ], 201);
    }
}
