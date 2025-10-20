<?php

use Database\Common\DatabaseConnections as DB_CONN;
use Database\Common\MigrationAddIndexesToAuditTableTrait;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    protected $connection = DB_CONN::AUDIT;

    public $withinTransaction = false;

    use MigrationAddIndexesToAuditTableTrait;

    protected function getTableName(): string
    {
        return 'course_audits';
    }
};
