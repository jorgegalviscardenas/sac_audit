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
        if (! Schema::connection($this->connection)->hasTable('user_system_work_group')) {
            Schema::connection($this->connection)->create('user_system_work_group', function (Blueprint $table) {
                $table->uuid('user_system_id');
                $table->uuid('work_group_id');
                $table->timestamps();

                $table->foreign('user_system_id')
                    ->references('id')
                    ->on('user_systems')
                    ->onDelete('cascade');

                $table->foreign('work_group_id')
                    ->references('id')
                    ->on('work_groups')
                    ->onDelete('cascade');

                $table->primary(['user_system_id', 'work_group_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('user_system_work_group');
    }
};
