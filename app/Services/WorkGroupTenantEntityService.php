<?php

namespace App\Services;

use App\DTOs\EntityConfigDTO;
use App\Models\Entity;
use App\Models\UserSystem;
use App\Models\WorkGroupTenant;
use App\Models\WorkGroupTenantEntity;

class WorkGroupTenantEntityService
{
    /**
     * Get entities configured for a user and tenant.
     *
     * @return EntityConfigDTO[]
     */
    public function getEntitiesForUserAndTenant(string $userId, string $tenantId): array
    {
        $userSystem = UserSystem::find($userId);

        if (! $userSystem) {
            return [];
        }

        // Get all work group IDs for the user
        $workGroupIds = $userSystem->workGroups()->pluck('id');

        // Find the work group tenant for this tenant and any of the user's work groups
        $workGroupTenant = WorkGroupTenant::whereIn('work_group_id', $workGroupIds)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $workGroupTenant) {
            return [];
        }

        // Get all entity IDs linked to this work group-tenant
        $entityIds = WorkGroupTenantEntity::where('work_group_tenant_id', $workGroupTenant->id)
            ->pluck('entity_id')
            ->toArray();

        // Get entities and create DTOs
        $entities = Entity::whereIn('id', $entityIds)->get();

        return $entities->map(function (Entity $entity) {
            return new EntityConfigDTO(
                entity: $entity,
                fields: []
            );
        })->toArray();
    }
}
