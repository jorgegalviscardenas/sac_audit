<?php

namespace App\Models;

use App\DTOs\CurrentTenantDTO;
use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserSystem extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $connection = DB_CONN::AUDIT;

    public ?CurrentTenantDTO $currentTenant = null;

    protected $fillable = [
        'names',
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
        return "$this->names $this->last_name";
    }

    public function workGroups(): BelongsToMany
    {
        return $this->belongsToMany(WorkGroup::class, 'user_system_work_group');
    }
}
