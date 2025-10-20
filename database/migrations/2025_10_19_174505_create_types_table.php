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
        if (! Schema::connection($this->connection)->hasTable('audit_types')) {
            Schema::connection($this->connection)->create('audit_types', function (Blueprint $table) {
                $table->smallInteger('id')->primary();
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
        Schema::connection($this->connection)->dropIfExists('audit_types');
    }
};
