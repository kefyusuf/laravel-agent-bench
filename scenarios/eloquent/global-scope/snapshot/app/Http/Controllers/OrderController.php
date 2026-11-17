<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'orders' => Order::latest('id')->get(['id', 'reference'])->map(fn ($o) => [
                'id' => $o->id,
                'reference' => $o->reference,
            ]),
        ]);
    }
}
