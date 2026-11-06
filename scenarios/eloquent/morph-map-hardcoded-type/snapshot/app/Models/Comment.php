<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    protected $fillable = ['commentable_type', 'commentable_id', 'body', 'approved'];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
        ];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
