<?php

namespace Database\Common;

use Illuminate\Support\Facades\DB;

trait MigrationAddIndexesToAuditTableTrait
{
    protected $connection = 'audit';

    public function up(): void
    {
        $this->createIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = $this->getTableName();
        DB::connection($this->connection)->statement("DROP INDEX IF EXISTS {$tableName}_tenant_created_at_type_object_id");
        DB::connection($this->connection)->statement("DROP INDEX IF EXISTS {$tableName}_tenant_type_object_id");
        DB::connection($this->connection)->statement("DROP INDEX IF EXISTS {$tableName}_tenant_object_id");
    }

    protected function createIndexes(): void
    {
        $tableName = $this->getTableName();
        DB::connection($this->connection)->statement("
              CREATE INDEX IF NOT EXISTS {$tableName}_tenant_created_at_type_object_id
              ON {$tableName} (tenant_id, created_at, type, object_id)
          ");

        DB::connection($this->connection)->statement("
              CREATE INDEX IF NOT EXISTS {$tableName}_tenant_type_object_id
              ON {$tableName} (tenant_id, type, object_id)
          ");

        DB::connection($this->connection)->statement("
              CREATE INDEX IF NOT EXISTS {$tableName}_tenant_object_id
              ON {$tableName} (tenant_id, object_id)
          ");
    }

    abstract protected function getTableName(): string;
}
