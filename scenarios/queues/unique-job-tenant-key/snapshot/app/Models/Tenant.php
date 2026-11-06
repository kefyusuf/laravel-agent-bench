<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'last_report_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_report_generated_at' => 'datetime',
        ];
    }
}
