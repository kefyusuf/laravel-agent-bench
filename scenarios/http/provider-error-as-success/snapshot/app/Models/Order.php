<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    protected $fillable = ['customer_name', 'total_cents', 'status', 'provider_reference'];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
        ];
    }
}
