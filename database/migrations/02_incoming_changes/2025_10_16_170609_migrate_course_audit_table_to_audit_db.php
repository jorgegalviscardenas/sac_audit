<?php

use Database\Common\DatabaseConnections as DB_CONN;
use Database\Common\MigrationCopyDataToAuditTableTrait;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    use MigrationCopyDataToAuditTableTrait;

    protected $connection = DB_CONN::AUDIT;

    protected function getTableName(): string
    {
        return 'course_audits';
    }
};
