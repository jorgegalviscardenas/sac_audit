<?php

namespace App\Services;

use App\DTOs\CurrentTenantDTO;
use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroupTenant;

class UserSystemTenantService
{
    public function __construct(
        private readonly WorkGroupTenantEntityService $workGroupTenantEntityService
    ) {}

    /**
     * Get a specific tenant for a user if they have access to it.
     */
    public function getTenantWithEntities(string $userId, string $tenantId): ?CurrentTenantDTO
    {
        $userSystem = UserSystem::find($userId);

        if (! $userSystem) {
            return null;
        }

        // Get all work group IDs for the user
        $workGroupIds = $userSystem->workGroups()->pluck('id');

        // Check if the tenant is linked to any of the user's work groups
        $hasAccess = WorkGroupTenant::whereIn('work_group_id', $workGroupIds)
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

        // Get entities for this user and tenant
        $entities = $this->workGroupTenantEntityService->getEntitiesForUserAndTenant($userId, $tenantId);

        return new CurrentTenantDTO($tenant, $entities);
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
        $workGroupTenant = WorkGroupTenant::where('work_group_id', $firstWorkGroup->id)
            ->orderBy('created_at')
            ->first();

        if (! $workGroupTenant) {
            return null;
        }

        $tenant = $workGroupTenant->getTenant();

        if (! $tenant) {
            return null;
        }

        // Get entities for this user and tenant
        $entities = $this->workGroupTenantEntityService->getEntitiesForUserAndTenant($userId, (string) $tenant->id);

        return new CurrentTenantDTO($tenant, $entities);
    }

    /**
     * Get tenants from array of IDs with optional filtering and pagination.
     */
    private function getTenantsFromIds(array $ids, ?string $search = null, ?int $page = null, int $perPage = 10): array
    {
        $query = Tenant::whereIn('id', $ids);

        // Apply search filter
        if ($search) {
            $query->where('name', 'ILIKE', '%'.$search.'%');
        }

        // Apply pagination if requested
        if ($page !== null) {
            $offset = ($page - 1) * $perPage;
            $query->skip($offset)->take($perPage);
        }

        return $query->get()->toArray();
    }

    /**
     * Get total count of tenants from array of IDs with optional filtering.
     */
    public function getTenantsCount(array $ids, ?string $search = null): int
    {
        $query = Tenant::whereIn('id', $ids);

        if ($search) {
            $query->where('name', 'ILIKE', '%'.$search.'%');
        }

        return $query->count();
    }

    /**
     * Get paginated tenants for a user with optional search.
     */
    public function getPaginatedTenants(string $userId, ?string $search = null, int $page = 1, int $perPage = 10): array
    {
        $userSystem = UserSystem::find($userId);

        if (! $userSystem) {
            return [
                'data' => [],
                'total' => 0,
                'has_more' => false,
            ];
        }

        // Get all work group IDs for the user
        $workGroupIds = $userSystem->workGroups()->pluck('id');

        // Get all unique tenant IDs from work_group_tenants
        $tenantIds = WorkGroupTenant::whereIn('work_group_id', $workGroupIds)
            ->distinct()
            ->pluck('tenant_id')
            ->toArray();

        // Get total count
        $total = $this->getTenantsCount($tenantIds, $search);

        // Get paginated data
        $tenants = $this->getTenantsFromIds($tenantIds, $search, $page, $perPage);

        return [
            'data' => $tenants,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
        ];
    }
}
