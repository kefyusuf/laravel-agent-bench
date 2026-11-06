<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;

class CustomerInvoiceController extends Controller
{
    public function show(Customer $customer, Invoice $invoice)
    {
        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'total_cents' => $invoice->total_cents,
            ],
        ]);
    }
}
