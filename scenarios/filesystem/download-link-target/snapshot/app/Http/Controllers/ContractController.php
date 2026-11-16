<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'contents' => ['required', 'string'],
        ]);

        $path = 'contracts/' . uniqid('contract-', true) . '.txt';
        Storage::disk('contracts')->put($path, $data['contents']);

        $contract = Contract::create([
            'customer_name' => $data['customer_name'],
            'path' => $path,
        ]);

        return response()->json(['contract' => ['id' => $contract->id]], 201);
    }

    public function link(Contract $contract): JsonResponse
    {
        return response()->json([
            'url' => Storage::disk('contracts')->url($contract->path),
        ]);
    }

    public function download(Contract $contract)
    {
        return Storage::disk('contracts')->download($contract->path);
    }
}
