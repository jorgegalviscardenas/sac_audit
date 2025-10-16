<?php

use Database\Common\MigrationAddIndexesToAuditTableTrait;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    protected $connection = 'audit';

    public $withinTransaction = false;

    use MigrationAddIndexesToAuditTableTrait;

    protected function getTableName(): string
    {
        return 'user_audits';
    }
};
