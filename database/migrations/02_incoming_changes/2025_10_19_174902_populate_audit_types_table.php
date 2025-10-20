<?php

use App\Models\AuditType;
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
        $auditTypes = [
            [
                'id' => 1,
                'name' => 'Crear',
            ],
            [
                'id' => 2,
                'name' => 'Actualizar',
            ],
            [
                'id' => 3,
                'name' => 'Eliminar',
            ],
        ];

        foreach ($auditTypes as $typeData) {
            AuditType::updateOrCreate(
                ['id' => $typeData['id']],
                $typeData
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        AuditType::query()->delete();
    }
};
