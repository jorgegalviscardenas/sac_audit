<?php

use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    protected $connection = DB_CONN::OPERATIONAL;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('users')) {
            Schema::connection($this->connection)->create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->string('email')->unique();
                $table->string('full_name', 150);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('users');
    }
};
