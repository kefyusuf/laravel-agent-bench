<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    protected $fillable = ['external_id', 'total_cents'];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
        ];
    }
}
