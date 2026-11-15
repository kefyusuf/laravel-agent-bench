<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public const KIND_REFUND = 'refund';

    protected $fillable = ['subscription_id', 'kind', 'amount_cents'];

    protected function casts(): array
    {
        return [
            'subscription_id' => 'integer',
            'amount_cents' => 'integer',
        ];
    }
}
