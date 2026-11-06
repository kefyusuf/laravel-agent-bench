<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    protected $fillable = ['customer_id', 'amount_cents', 'provider_reference'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }
}
