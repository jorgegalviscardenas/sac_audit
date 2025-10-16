<?php

namespace Database\Common;

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

trait MigrationCreateAuditTableTrait
{
    public function up(): void
    {
        $this->executeTableMigration();
    }

    protected function executeTableMigration(): void
    {
        // For operational connection, create normal table
        $this->getConnectionDB()->create($this->getTableName(), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->uuid('object_id');
            $table->smallInteger('type');
            $table->jsonb('diffs');
            $table->string('transaction_hash');
            $table->string('blame_id');
            $table->string('blame_user');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->getConnectionDB()->dropIfExists($this->getTableName());
    }

    protected function getConnectionDB(): Builder
    {
        return Schema::connection(DB_CONN::OPERATIONAL);
    }

    abstract protected function getTableName(): string;
}
