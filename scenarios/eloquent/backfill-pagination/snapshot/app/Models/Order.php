<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['reference', 'backfilled_at'];

    protected function casts(): array
    {
        return [
            'backfilled_at' => 'datetime',
        ];
    }
}
