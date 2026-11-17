<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        if ($request->query('include') === 'customer') {
            $invoice->load('customer');
        }

        return new InvoiceResource($invoice);
    }
}
