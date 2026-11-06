<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Services\BillingGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargeController extends Controller
{
    public function __invoke(Request $request, BillingGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $reference = $gateway->charge($data['customer_id'], $data['amount_cents']);

        $charge = Charge::create([
            'customer_id' => $data['customer_id'],
            'amount_cents' => $data['amount_cents'],
            'provider_reference' => $reference,
        ]);

        return response()->json(['charge' => ['id' => $charge->id, 'reference' => $reference]]);
    }
}
