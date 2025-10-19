<?php

namespace App\Services;

use App\DTOs\CurrentTenantDTO;
use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroupTenantEntity;

class UserSystemTenantService
{
    /**
     * Get all tenants linked to a user system through work groups.
     */
    public function getTenants(string $userId): array
    {
        $userSystem = UserSystem::find($userId);

        if (! $userSystem) {
            return [];
        }

        // Get all work group IDs for the user
        $workGroupIds = $userSystem->workGroups()->pluck('id');

        // Get all unique tenant IDs from work_group_tenant_entities
        $tenantIds = WorkGroupTenantEntity::whereIn('work_group_id', $workGroupIds)
            ->distinct()
            ->pluck('tenant_id')
            ->toArray();

        return $this->getTenantsFromIds($tenantIds);
    }

    /**
     * Get a specific tenant for a user if they have access to it.
     */
    public function getTenant(string $userId, string $tenantId): ?CurrentTenantDTO
    {
        $userSystem = UserSystem::find($userId);

        if (! $userSystem) {
            return null;
        }

        // Get all work group IDs for the user
        $workGroupIds = $userSystem->workGroups()->pluck('id');

        // Check if the tenant is linked to any of the user's work groups
        $hasAccess = WorkGroupTenantEntity::whereIn('work_group_id', $workGroupIds)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $hasAccess) {
            return null;
        }

        // Get the tenant
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return null;
        }

        return new CurrentTenantDTO($tenant);
    }

    /**
     * Get the first tenant for a user based on their first work group.
     */
    public function getFirstTenant(string $userId): ?CurrentTenantDTO
    {
        $userSystem = UserSystem::find($userId);

        if (! $userSystem) {
            return null;
        }

        // Get first work group of the user (ordered by created_at)
        /** @var \App\Models\WorkGroup|null $firstWorkGroup */
        $firstWorkGroup = $userSystem->workGroups()->orderBy('created_at')->first();

        if (! $firstWorkGroup) {
            return null;
        }

        // Get first tenant linked to the work group (ordered by created_at)
        $workGroupTenantEntity = WorkGroupTenantEntity::where('work_group_id', $firstWorkGroup->id)
            ->orderBy('created_at')
            ->first();

        if (! $workGroupTenantEntity) {
            return null;
        }

        $tenant = $workGroupTenantEntity->getTenant();

        if (! $tenant) {
            return null;
        }

        return new CurrentTenantDTO($tenant);
    }

    /**
     * Get tenants from array of IDs.
     */
    private function getTenantsFromIds(array $ids): array
    {
        return Tenant::whereIn('id', $ids)->get()->toArray();
    }
}
