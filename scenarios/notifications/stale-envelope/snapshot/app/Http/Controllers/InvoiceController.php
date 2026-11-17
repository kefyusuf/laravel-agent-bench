<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Notifications\InvoicePaidNotification;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function markPaid(Invoice $invoice): JsonResponse
    {
        $invoice->forceFill(['status' => 'paid'])->save();

        $customer = Customer::findOrFail($invoice->customer_id);
        $customer->notify(new InvoicePaidNotification($invoice, $customer->billing_email));

        return response()->json(['invoice' => ['number' => $invoice->number, 'status' => 'paid']]);
    }
}
