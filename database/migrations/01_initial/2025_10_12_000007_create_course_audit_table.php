<?php

use Database\Common\DatabaseConnections as DB_CONN;
use Database\Common\MigrationCreateAuditTableTrait;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    use MigrationCreateAuditTableTrait;

    protected $connection = DB_CONN::OPERATIONAL;

    protected function getTableName(): string
    {
        return 'course_audits';
    }
};
