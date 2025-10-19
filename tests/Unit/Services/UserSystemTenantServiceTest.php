<?php

namespace Tests\Unit\Services;

use App\DTOs\CurrentTenantDTO;
use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenantEntity;
use App\Services\UserSystemTenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSystemTenantServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserSystemTenantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserSystemTenantService();
    }

    public function test_get_tenants_returns_empty_array_for_non_existent_user(): void
    {
        $result = $this->service->getTenants('00000000-0000-0000-0000-000000000999');

        $this->assertEmpty($result);
    }

    public function test_get_tenants_returns_tenants_for_user(): void
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
        WorkGroupTenantEntity::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        $result = $this->service->getTenants($userSystem->id);

        $this->assertCount(1, $result);
        $this->assertEquals($tenant->id, $result[0]['id']);
    }

    public function test_get_tenant_returns_null_for_non_existent_user(): void
    {
        $tenant = Tenant::factory()->create();

        $result = $this->service->getTenant('00000000-0000-0000-0000-000000000999', $tenant->id);

        $this->assertNull($result);
    }

    public function test_get_tenant_returns_null_when_user_has_no_access(): void
    {
        $userSystem = UserSystem::factory()->create();
        $tenant = Tenant::factory()->create();

        $result = $this->service->getTenant($userSystem->id, $tenant->id);

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
        WorkGroupTenantEntity::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        $result = $this->service->getTenant($userSystem->id, $tenant->id);

        $this->assertInstanceOf(CurrentTenantDTO::class, $result);
        $this->assertEquals($tenant->id, $result->tenant->id);
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
        WorkGroupTenantEntity::create([
            'work_group_id' => $workGroup1->id,
            'tenant_id' => $tenant1->id,
            'created_at' => now()->subHour(),
        ]);

        WorkGroupTenantEntity::create([
            'work_group_id' => $workGroup1->id,
            'tenant_id' => $tenant2->id,
            'created_at' => now(),
        ]);

        $result = $this->service->getFirstTenant($userSystem->id);

        $this->assertInstanceOf(CurrentTenantDTO::class, $result);
        $this->assertEquals($tenant1->id, $result->tenant->id);
    }
}
