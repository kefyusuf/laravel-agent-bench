<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use Notifiable;

    protected $fillable = ['name', 'billing_email'];

    public function routeNotificationForMail(): string
    {
        return $this->billing_email;
    }
}
