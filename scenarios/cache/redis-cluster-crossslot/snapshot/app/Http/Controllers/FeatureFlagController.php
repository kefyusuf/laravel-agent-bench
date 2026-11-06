<?php

namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;

class FeatureFlagController extends Controller
{
    public function __construct(private FeatureFlagService $flags)
    {
    }

    public function show(string $tenant): JsonResponse
    {
        return response()->json([
            'tenant' => $tenant,
            'enabled' => $this->flags->get($tenant) === '1',
            'uses' => $this->flags->uses($tenant),
        ]);
    }

    public function release(string $tenant, FeatureFlagService $flags): JsonResponse
    {
        $flags->releaseVersion($tenant, now()->timestamp);

        return response()->json(['message' => 'released']);
    }
}
