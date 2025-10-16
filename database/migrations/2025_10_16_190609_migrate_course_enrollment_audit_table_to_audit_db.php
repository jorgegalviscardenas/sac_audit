<?php

use Database\Common\MigrationCopyDataToAuditTableTrait;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    use MigrationCopyDataToAuditTableTrait;

    protected $connection = 'audit';

    protected function getTableName(): string
    {
        return 'course_enrollment_audits';
    }
};
