<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'operational';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('operational')->create('course_enrollment_audits', function (Blueprint $table) {
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
        Schema::connection('operational')->dropIfExists('course_enrollment_audits');
    }
};
