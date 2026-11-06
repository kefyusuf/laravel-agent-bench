<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = ['sku', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
