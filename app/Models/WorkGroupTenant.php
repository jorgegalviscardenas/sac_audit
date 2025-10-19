<?php

namespace App\Models;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkGroupTenant extends Model
{
    use HasUuids;

    protected $connection = DB_CONN::AUDIT;

    protected $fillable = [
        'work_group_id',
        'tenant_id',
    ];

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }

    /**
     * Get the tenant from operational database.
     * Note: This is not a standard Eloquent relationship due to cross-database constraint.
     */
    public function getTenant(): ?Tenant
    {
        return Tenant::find($this->tenant_id);
    }

    public function workGroupTenantEntities(): HasMany
    {
        return $this->hasMany(WorkGroupTenantEntity::class);
    }
}
