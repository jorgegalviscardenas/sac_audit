<?php

namespace App\Models;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkGroupTenantEntity extends Model
{
    use HasUuids;

    protected $connection = DB_CONN::AUDIT;

    protected $fillable = [
        'work_group_tenant_id',
        'entity_id',
    ];

    public function workGroupTenant(): BelongsTo
    {
        return $this->belongsTo(WorkGroupTenant::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
