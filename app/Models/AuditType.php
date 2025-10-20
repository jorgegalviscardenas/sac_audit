<?php

namespace App\Models;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditType extends Model
{
    use HasUuids;

    protected $connection = DB_CONN::AUDIT;

    protected $fillable = [
        'id',
        'name',
    ];
}
