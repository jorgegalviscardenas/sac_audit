<?php

namespace App\Models;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserSystem extends Authenticatable
{
    use Notifiable;

    protected $connection = DB_CONN::AUDIT;

    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getFullName(): string
    {
        return "$this->name $this->last_name";
    }
}
