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
        if (! Schema::connection($this->connection)->hasTable('tenants')) {
            Schema::connection($this->connection)->create('tenants', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenants');
    }
};
