<?php

namespace App\Models;

use App\Models\Scopes\ArchivedScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['reference', 'archived_at'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ArchivedScope);
    }

    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ArchivedScope::class);
    }
}
