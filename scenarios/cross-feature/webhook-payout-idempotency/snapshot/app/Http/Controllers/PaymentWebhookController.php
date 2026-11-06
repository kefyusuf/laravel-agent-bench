<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayout;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = (string) config('services.payrail.webhook_secret');
        $signature = (string) $request->header('X-PayRail-Signature', '');

        if ($secret === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();

        DB::transaction(function () use ($payload) {
            $event = PaymentEvent::firstOrCreate(
                ['provider_event_id' => $payload['id']],
                [
                    'order_id' => $payload['data']['order_id'],
                    'payload' => $payload,
                ],
            );

            if ($event->wasRecentlyCreated) {
                $order = Order::findOrFail($payload['data']['order_id']);
                $order->markPaid();

                ProcessPayout::dispatch($order->id);
            }
        });

        return response()->json(['message' => 'accepted']);
    }
}
