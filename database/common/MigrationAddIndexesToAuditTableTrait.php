<?php

namespace Database\Common;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Support\Facades\DB;

trait MigrationAddIndexesToAuditTableTrait
{
    protected $connectionDB = DB_CONN::AUDIT;

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
        DB::connection($this->connectionDB)->statement("DROP INDEX IF EXISTS {$tableName}_tenant_created_at_type_object_id");
        DB::connection($this->connectionDB)->statement("DROP INDEX IF EXISTS {$tableName}_tenant_type_object_id");
        DB::connection($this->connectionDB)->statement("DROP INDEX IF EXISTS {$tableName}_tenant_object_id");
    }

    protected function createIndexes(): void
    {
        $tableName = $this->getTableName();
        DB::connection($this->connectionDB)->statement("
              CREATE INDEX IF NOT EXISTS {$tableName}_tenant_created_at_type_object_id
              ON {$tableName} (tenant_id, created_at, type, object_id)
          ");

        DB::connection($this->connectionDB)->statement("
              CREATE INDEX IF NOT EXISTS {$tableName}_tenant_type_object_id
              ON {$tableName} (tenant_id, type, object_id)
          ");

        DB::connection($this->connectionDB)->statement("
              CREATE INDEX IF NOT EXISTS {$tableName}_tenant_object_id
              ON {$tableName} (tenant_id, object_id)
          ");
    }

    abstract protected function getTableName(): string;
}
