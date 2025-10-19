<?php

namespace App\Models;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkGroup extends Model
{
    use HasFactory, HasUuids;

    protected $connection = DB_CONN::AUDIT;

    protected $fillable = [
        'name',
    ];

    public function userSystems(): BelongsToMany
    {
        return $this->belongsToMany(UserSystem::class, 'user_system_work_group');
    }

    public function workGroupTenants(): HasMany
    {
        return $this->hasMany(WorkGroupTenant::class);
    }
}
