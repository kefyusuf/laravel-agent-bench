<?php

namespace App\Http\Controllers;

use App\Jobs\SendDigestJob;
use App\Models\Digest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DigestController extends Controller
{
    public function send(Request $request, int $customer): JsonResponse
    {
        $digest = Digest::create([
            'customer_id' => $customer,
            'status' => Digest::STATUS_PENDING,
        ]);

        SendDigestJob::dispatch($digest);

        return response()->json(['digest' => ['id' => $digest->id, 'status' => $digest->status]]);
    }
}
