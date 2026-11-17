<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OpsOrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'orders' => Order::latest('id')->get(['id', 'reference', 'archived_at'])->map(fn ($o) => [
                'id' => $o->id,
                'reference' => $o->reference,
                'archived' => $o->archived_at !== null,
            ]),
        ]);
    }
}
