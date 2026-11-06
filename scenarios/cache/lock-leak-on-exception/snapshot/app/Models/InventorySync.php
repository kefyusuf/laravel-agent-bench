<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventorySync extends Model
{
    protected $fillable = ['item_count'];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
        ];
    }
}
