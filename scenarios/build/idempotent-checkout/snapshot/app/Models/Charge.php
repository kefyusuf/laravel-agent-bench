<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    protected $fillable = ['order_id', 'customer_id', 'reference', 'amount_cents'];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'amount_cents' => 'integer',
        ];
    }
}
