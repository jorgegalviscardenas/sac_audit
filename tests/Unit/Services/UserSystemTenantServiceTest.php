<?php

namespace Tests\Unit\Services;

use App\DTOs\CurrentTenantDTO;
use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenant;
use App\Services\UserSystemTenantService;
use App\Services\WorkGroupTenantEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSystemTenantServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserSystemTenantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $workGroupTenantEntityService = $this->createMock(WorkGroupTenantEntityService::class);
        $workGroupTenantEntityService->method('getEntitiesForUserAndTenant')
            ->willReturn([]);

        $this->service = new UserSystemTenantService($workGroupTenantEntityService);
    }

    public function test_get_paginated_tenants_returns_empty_array_for_non_existent_user(): void
    {
        $result = $this->service->getPaginatedTenants('00000000-0000-0000-0000-000000000999');

        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['total']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_paginated_tenants_returns_tenants_for_user(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $userSystem->workGroups()->attach($workGroup->id);

        // Create tenant
        $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);

        // Link work group to tenant
        WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        $result = $this->service->getPaginatedTenants($userSystem->id);

        $this->assertCount(1, $result['data']);
        $this->assertEquals($tenant->id, $result['data'][0]['id']);
        $this->assertEquals(1, $result['total']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_paginated_tenants_filters_by_search(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $userSystem->workGroups()->attach($workGroup->id);

        // Create tenants
        $tenant1 = Tenant::factory()->create(['name' => 'Apple Inc']);
        $tenant2 = Tenant::factory()->create(['name' => 'Banana Corp']);

        // Link work group to tenants
        WorkGroupTenant::create(['work_group_id' => $workGroup->id, 'tenant_id' => $tenant1->id]);
        WorkGroupTenant::create(['work_group_id' => $workGroup->id, 'tenant_id' => $tenant2->id]);

        $result = $this->service->getPaginatedTenants($userSystem->id, 'Apple');

        $this->assertCount(1, $result['data']);
        $this->assertEquals($tenant1->id, $result['data'][0]['id']);
        $this->assertEquals(1, $result['total']);
    }

    public function test_get_paginated_tenants_paginates_results(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $userSystem->workGroups()->attach($workGroup->id);

        // Create 15 tenants
        for ($i = 1; $i <= 15; $i++) {
            $tenant = Tenant::factory()->create(['name' => "Tenant $i"]);
            WorkGroupTenant::create(['work_group_id' => $workGroup->id, 'tenant_id' => $tenant->id]);
        }

        // Get page 1 (10 items)
        $result = $this->service->getPaginatedTenants($userSystem->id, null, 1, 10);

        $this->assertCount(10, $result['data']);
        $this->assertEquals(15, $result['total']);
        $this->assertTrue($result['has_more']);

        // Get page 2 (5 items)
        $result = $this->service->getPaginatedTenants($userSystem->id, null, 2, 10);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(15, $result['total']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_tenant_returns_null_for_non_existent_user(): void
    {
        $tenant = Tenant::factory()->create();

        $result = $this->service->getTenantWithEntities('00000000-0000-0000-0000-000000000999', $tenant->id);

        $this->assertNull($result);
    }

    public function test_get_tenant_returns_null_when_user_has_no_access(): void
    {
        $userSystem = UserSystem::factory()->create();
        $tenant = Tenant::factory()->create();

        $result = $this->service->getTenantWithEntities($userSystem->id, $tenant->id);

        $this->assertNull($result);
    }

    public function test_get_tenant_returns_tenant_when_user_has_access(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $userSystem->workGroups()->attach($workGroup->id);

        // Create tenant
        $tenant = Tenant::factory()->create();

        // Link work group to tenant
        WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        $result = $this->service->getTenantWithEntities($userSystem->id, $tenant->id);

        $this->assertInstanceOf(CurrentTenantDTO::class, $result);
        $this->assertEquals($tenant->id, $result->tenant->id);
        $this->assertIsArray($result->entities);
    }

    public function test_get_first_tenant_returns_null_for_non_existent_user(): void
    {
        $result = $this->service->getFirstTenant('00000000-0000-0000-0000-000000000999');

        $this->assertNull($result);
    }

    public function test_get_first_tenant_returns_first_tenant_from_first_work_group(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work groups
        $workGroup1 = WorkGroup::factory()->create(['created_at' => now()->subDay()]);
        $workGroup2 = WorkGroup::factory()->create(['created_at' => now()]);

        // Link user to work groups
        $userSystem->workGroups()->attach([$workGroup1->id, $workGroup2->id]);

        // Create tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Link work groups to tenants
        WorkGroupTenant::create([
            'work_group_id' => $workGroup1->id,
            'tenant_id' => $tenant1->id,
            'created_at' => now()->subHour(),
        ]);

        WorkGroupTenant::create([
            'work_group_id' => $workGroup1->id,
            'tenant_id' => $tenant2->id,
            'created_at' => now(),
        ]);

        $result = $this->service->getFirstTenant($userSystem->id);

        $this->assertInstanceOf(CurrentTenantDTO::class, $result);
        $this->assertEquals($tenant1->id, $result->tenant->id);
        $this->assertIsArray($result->entities);
    }
}
