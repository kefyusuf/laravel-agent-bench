<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigestSetting extends Model
{
    protected $fillable = [
        'customer_id',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'attach_pdfs' => 'boolean',
        ];
    }
}
