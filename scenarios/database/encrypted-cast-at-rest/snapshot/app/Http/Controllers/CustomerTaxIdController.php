<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTaxIdController extends Controller
{
    public function show(Customer $customer): JsonResponse
    {
        return response()->json(['tax_id' => $customer->tax_id]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:64'],
        ]);

        $customer->forceFill(['tax_id' => $data['tax_id']])->save();

        return response()->json(['tax_id' => $customer->tax_id]);
    }
}
