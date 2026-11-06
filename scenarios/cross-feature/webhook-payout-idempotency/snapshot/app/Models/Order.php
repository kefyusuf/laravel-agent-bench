<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'seller_id',
        'total_cents',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function markPaid(): void
    {
        if ($this->status === 'refunded') {
            throw new \InvalidArgumentException('cannot mark a refunded order as paid');
        }

        $this->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();
    }
}
