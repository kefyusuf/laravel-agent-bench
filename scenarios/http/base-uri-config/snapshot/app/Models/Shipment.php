<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = ['order_id', 'provider_reference', 'destination'];

    public function scopeLatest($query)
    {
        return $query->orderBy('id', 'desc');
    }
}
