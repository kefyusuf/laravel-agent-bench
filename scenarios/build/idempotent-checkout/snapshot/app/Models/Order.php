<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    protected $fillable = ['customer_id', 'total_cents', 'status'];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function markPaid(): void
    {
        $this->forceFill(['status' => self::STATUS_PAID])->save();
    }
}
