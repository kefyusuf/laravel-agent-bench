<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'owner_id',
        'filename',
        'status',
        'transcoded_at',
    ];

    protected function casts(): array
    {
        return [
            'transcoded_at' => 'datetime',
        ];
    }
}
