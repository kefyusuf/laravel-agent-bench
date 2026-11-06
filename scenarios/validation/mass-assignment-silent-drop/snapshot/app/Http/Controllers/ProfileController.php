<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'user' => [
                'name' => $user->name,
                'timezone' => $user->timezone,
                'marketing_opt_in' => $user->marketing_opt_in,
            ],
        ]);
    }
}
