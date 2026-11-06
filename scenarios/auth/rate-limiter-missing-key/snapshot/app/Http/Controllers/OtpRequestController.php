<?php

namespace App\Http\Controllers;

use App\Models\OtpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpRequestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        OtpRequest::create([
            'email' => $data['email'],
            'ip' => $request->ip(),
        ]);

        // A queued job would deliver the passcode; delivery is irrelevant here.

        return response()->json(['message' => 'passcode sent']);
    }
}
