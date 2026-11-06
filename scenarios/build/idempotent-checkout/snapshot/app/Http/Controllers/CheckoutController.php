<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
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

        $response->throw();

        $reference = (string) $response->json('reference');

        if ($reference === '') {
            abort(502, 'provider did not return a payment reference');
        }

        Charge::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'reference' => $reference,
            'amount_cents' => $order->total_cents,
        ]);

        $order->markPaid();

        return response()->json([
            'order' => ['id' => $order->id, 'status' => $order->status],
            'reference' => $reference,
        ]);
    }
}
