<?php

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    protected $connection = DB_CONN::AUDIT;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('work_group_tenant_entities')) {
            Schema::connection($this->connection)->create('work_group_tenant_entities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('work_group_tenant_id');
                $table->uuid('entity_id');
                $table->timestamps();

                $table->foreign('work_group_tenant_id')
                    ->references('id')
                    ->on('work_group_tenants')
                    ->onDelete('cascade');

                $table->foreign('entity_id')
                    ->references('id')
                    ->on('entities')
                    ->onDelete('cascade');

                $table->unique(['work_group_tenant_id', 'entity_id']);
                $table->index('entity_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('work_group_tenant_entities');
    }
};
