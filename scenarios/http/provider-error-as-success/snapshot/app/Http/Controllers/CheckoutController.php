<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = Order::findOrFail($data['order_id']);

        $response = Http::asJson()
            ->withToken((string) config('services.paylane.key', 'test'))
            ->post('https://api.paylane.example/v1/charges', [
                'order_id' => $order->id,
                'amount_cents' => $order->total_cents,
            ]);

        $reference = $response->json('reference');

        $order->forceFill([
            'status' => Order::STATUS_PAID,
            'provider_reference' => $reference,
        ])->save();

        return response()->json([
            'order' => ['id' => $order->id, 'status' => $order->status],
            'reference' => $reference,
        ]);
    }
}
