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
        if (! Schema::connection($this->connection)->hasTable('work_group_tenants')) {
            Schema::connection($this->connection)->create('work_group_tenants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('work_group_id');
                $table->uuid('tenant_id');
                $table->timestamps();

                $table->foreign('work_group_id')
                    ->references('id')
                    ->on('work_groups')
                    ->onDelete('cascade');

                $table->unique(['work_group_id', 'tenant_id']);
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('work_group_tenants');
    }
};
