<?php

namespace App\Models;

use App\Models\Common\Auditable;
use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Model;

class UserAudit extends Model
{
    use Auditable;

    protected $connection = DB_CONN::AUDIT;

    protected $table = 'user_audits';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'object_id',
        'type',
        'diffs',
        'transaction_hash',
        'blame_id',
        'blame_user',
        'created_at',
    ];

    protected $casts = [
        'diffs' => 'array',
        'type' => 'integer',
        'created_at' => 'datetime',
    ];
}
