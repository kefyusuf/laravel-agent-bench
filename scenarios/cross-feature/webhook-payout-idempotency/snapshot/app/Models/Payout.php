<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = [
        'order_id',
        'amount_cents',
        'provider_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }
}
