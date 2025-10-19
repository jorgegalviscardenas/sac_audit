<?php

namespace App\Models;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasUuids;

    protected $connection = DB_CONN::AUDIT;

    protected $fillable = [
        'id',
        'name',
        'model_class',
    ];

    public function workGroupTenantEntities(): HasMany
    {
        return $this->hasMany(WorkGroupTenantEntity::class);
    }
}
