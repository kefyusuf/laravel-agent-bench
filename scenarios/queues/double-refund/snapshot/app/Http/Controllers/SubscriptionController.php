<?php

namespace App\Http\Controllers;

use App\Jobs\SettleSubscriptionJob;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function cancel(Subscription $subscription): JsonResponse
    {
        $subscription->forceFill(['status' => 'cancelling'])->save();

        SettleSubscriptionJob::dispatch($subscription);

        return response()->json(['subscription' => ['id' => $subscription->id, 'status' => 'cancelling']]);
    }
}
