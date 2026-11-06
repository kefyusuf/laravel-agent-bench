<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDigestSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
            'locale' => ['required', 'string', 'max:12'],
            'attach_pdfs' => ['required', 'boolean'],
        ];
    }
}
