<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    protected $fillable = ['email', 'ip'];
}
