<?php

use App\Models\Entity;
use Database\Common\DatabaseConnections as DB_CONN;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    protected $connection = DB_CONN::AUDIT;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $entities = [
            [
                'id' => '00000000-0000-0000-0000-000000000001',
                'name' => 'Tenants',
                'model_class' => 'App\Models\TenantAudit',
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000002',
                'name' => 'Usuarios',
                'model_class' => 'App\Models\UserAudit',
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000003',
                'name' => 'Cursos',
                'model_class' => 'App\Models\CourseAudit',
            ],
            [
                'id' => '00000000-0000-0000-0000-000000000004',
                'name' => 'Inscripciones a Cursos',
                'model_class' => 'App\Models\CourseEnrollmentAudit',
            ],
        ];

        foreach ($entities as $entityData) {
            Entity::updateOrCreate(
                ['id' => $entityData['id']],
                $entityData
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Entity::query()->delete();
    }
};
