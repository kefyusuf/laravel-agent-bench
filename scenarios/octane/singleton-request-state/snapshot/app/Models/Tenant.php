<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
