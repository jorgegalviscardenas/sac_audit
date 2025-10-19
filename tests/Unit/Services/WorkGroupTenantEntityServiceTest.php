<?php

namespace Tests\Unit\Services;

use App\DTOs\EntityConfigDTO;
use App\Models\Entity;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenant;
use App\Models\WorkGroupTenantEntity;
use App\Services\WorkGroupTenantEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkGroupTenantEntityServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkGroupTenantEntityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkGroupTenantEntityService();
    }

    public function test_get_entities_returns_empty_array_for_non_existent_user(): void
    {
        $result = $this->service->getEntitiesForUserAndTenant(
            '00000000-0000-0000-0000-000000000999',
            '00000000-0000-0000-0000-000000000888'
        );

        $this->assertEmpty($result);
    }

    public function test_get_entities_returns_empty_array_when_user_has_no_access_to_tenant(): void
    {
        $userSystem = UserSystem::factory()->create();
        $tenantId = '00000000-0000-0000-0000-000000000001';

        $result = $this->service->getEntitiesForUserAndTenant($userSystem->id, $tenantId);

        $this->assertEmpty($result);
    }

    public function test_get_entities_returns_entities_for_user_and_tenant(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $userSystem->workGroups()->attach($workGroup->id);

        // Create tenant ID
        $tenantId = '00000000-0000-0000-0000-000000000001';

        // Create entities
        $entity1 = Entity::create([
            'name' => 'Entity 1',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $entity2 = Entity::create([
            'name' => 'Entity 2',
            'model_class' => 'App\Models\UserAudit',
        ]);

        // Link work group to tenant
        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenantId,
        ]);

        // Link entities to work group tenant
        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity1->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity2->id,
        ]);

        $result = $this->service->getEntitiesForUserAndTenant($userSystem->id, $tenantId);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(EntityConfigDTO::class, $result[0]);
        $this->assertInstanceOf(EntityConfigDTO::class, $result[1]);
        $this->assertContains($entity1->id, [$result[0]->entity->id, $result[1]->entity->id]);
        $this->assertContains($entity2->id, [$result[0]->entity->id, $result[1]->entity->id]);
    }

    public function test_get_entities_returns_only_entities_linked_to_work_group_tenant(): void
    {
        // Create user system
        $userSystem = UserSystem::factory()->create();

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $userSystem->workGroups()->attach($workGroup->id);

        // Create tenant ID
        $tenantId = '00000000-0000-0000-0000-000000000001';

        // Create entities
        $linkedEntity = Entity::create([
            'name' => 'Linked Entity',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $unlinkedEntity = Entity::create([
            'name' => 'Unlinked Entity',
            'model_class' => 'App\Models\UserAudit',
        ]);

        // Link work group to tenant
        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenantId,
        ]);

        // Link only one entity to work group tenant
        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $linkedEntity->id,
        ]);

        $result = $this->service->getEntitiesForUserAndTenant($userSystem->id, $tenantId);

        $this->assertCount(1, $result);
        $this->assertEquals($linkedEntity->id, $result[0]->entity->id);
        $this->assertEquals('Linked Entity', $result[0]->entity->name);
    }
}
