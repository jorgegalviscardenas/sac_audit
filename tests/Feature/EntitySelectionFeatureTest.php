<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenant;
use App\Models\WorkGroupTenantEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitySelectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_tenant_contains_entities_array(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();
        $entity = Entity::create([
            'name' => 'Tenants',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        $response = $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $currentTenant = $response->getSession()->get('current_tenant');

        $this->assertNotNull($currentTenant);
        $this->assertIsArray($currentTenant->entities);
        $this->assertCount(1, $currentTenant->entities);
    }

    public function test_user_sees_only_entities_linked_to_their_work_group(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup1 = WorkGroup::factory()->create();
        $workGroup2 = WorkGroup::factory()->create();

        // User belongs to workGroup1 only
        $user->workGroups()->attach($workGroup1->id);

        $tenant = Tenant::factory()->create();

        // Create entities
        $entity1 = Entity::create([
            'name' => 'Entity 1',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $entity2 = Entity::create([
            'name' => 'Entity 2',
            'model_class' => 'App\Models\UserAudit',
        ]);

        $entity3 = Entity::create([
            'name' => 'Entity 3',
            'model_class' => 'App\Models\CourseAudit',
        ]);

        // WorkGroup1 has access to entity1 and entity2
        $workGroupTenant1 = WorkGroupTenant::create([
            'work_group_id' => $workGroup1->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant1->id,
            'entity_id' => $entity1->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant1->id,
            'entity_id' => $entity2->id,
        ]);

        // WorkGroup2 has access to entity3 (but user is not in this group)
        $workGroupTenant2 = WorkGroupTenant::create([
            'work_group_id' => $workGroup2->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant2->id,
            'entity_id' => $entity3->id,
        ]);

        $response = $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $currentTenant = $response->getSession()->get('current_tenant');

        $this->assertCount(2, $currentTenant->entities);

        $entityIds = array_map(fn ($e) => $e->entity->id, $currentTenant->entities);
        $this->assertContains($entity1->id, $entityIds);
        $this->assertContains($entity2->id, $entityIds);
        $this->assertNotContains($entity3->id, $entityIds);
    }

    public function test_entity_filter_dropdown_shows_correct_entities(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();

        $entity1 = Entity::create([
            'name' => 'Tenants',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $entity2 = Entity::create([
            'name' => 'Users',
            'model_class' => 'App\Models\UserAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity1->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity2->id,
        ]);

        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $response = $this->get('/audit');

        $response->assertStatus(200);
        $response->assertSee('Tenants');
        $response->assertSee('Users');
    }

    public function test_different_work_groups_can_have_different_entities_for_same_tenant(): void
    {
        // Create two users in different work groups
        $user1 = UserSystem::factory()->create();
        $user2 = UserSystem::factory()->create();

        $workGroup1 = WorkGroup::factory()->create();
        $workGroup2 = WorkGroup::factory()->create();

        $user1->workGroups()->attach($workGroup1->id);
        $user2->workGroups()->attach($workGroup2->id);

        $tenant = Tenant::factory()->create();

        // Create entities
        $entity1 = Entity::create([
            'name' => 'Entity 1',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $entity2 = Entity::create([
            'name' => 'Entity 2',
            'model_class' => 'App\Models\UserAudit',
        ]);

        // WorkGroup1 has access to entity1 only
        $workGroupTenant1 = WorkGroupTenant::create([
            'work_group_id' => $workGroup1->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant1->id,
            'entity_id' => $entity1->id,
        ]);

        // WorkGroup2 has access to entity2 only
        $workGroupTenant2 = WorkGroupTenant::create([
            'work_group_id' => $workGroup2->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant2->id,
            'entity_id' => $entity2->id,
        ]);

        // User 1 should see only entity1
        $response1 = $this->actingAs($user1, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $currentTenant1 = $response1->getSession()->get('current_tenant');
        $this->assertCount(1, $currentTenant1->entities);
        $this->assertEquals($entity1->id, $currentTenant1->entities[0]->entity->id);

        // User 2 should see only entity2
        $response2 = $this->actingAs($user2, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $currentTenant2 = $response2->getSession()->get('current_tenant');
        $this->assertCount(1, $currentTenant2->entities);
        $this->assertEquals($entity2->id, $currentTenant2->entities[0]->entity->id);
    }
}
