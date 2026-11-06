<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Digest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'customer_id',
        'status',
        'provider_digest_id',
    ];
}
