<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDigestSettingsRequest;
use App\Models\DigestSetting;
use Illuminate\Http\JsonResponse;

class DigestSettingsController extends Controller
{
    public function update(UpdateDigestSettingsRequest $request): JsonResponse
    {
        $setting = DigestSetting::updateOrCreate(
            ['customer_id' => $request->integer('customer_id')],
            $request->validated(),
        );

        return response()->json([
            'setting' => [
                'customer_id' => $setting->customer_id,
                'locale' => $setting->locale,
                'attach_pdfs' => $setting->attach_pdfs,
            ],
        ]);
    }
}
