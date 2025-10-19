<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Tenant;
use App\Models\TenantAudit;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenant;
use App\Models\WorkGroupTenantEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFilterFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_page_displays_entity_filter_when_tenant_is_selected(): void
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

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $response = $this->get('/audit');

        $response->assertStatus(200);
        $response->assertSee('Tenants');
        $response->assertSee(__('audit.filter_entity'));
    }

    public function test_user_can_filter_audits_by_entity(): void
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

        // Create some audit records
        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000001',
            'type' => 1,
            'diffs' => ['name' => 'Test'],
            'transaction_hash' => 'hash123',
            'blame_id' => 'user1',
            'blame_user' => 'Test User',
            'created_at' => now(),
        ]);

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $response = $this->get('/audit?entity_id='.$entity->id);

        $response->assertStatus(200);
        $response->assertSee('Test User');
    }

    public function test_pagination_maintains_entity_filter(): void
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

        // Create multiple audit records
        for ($i = 0; $i < 20; $i++) {
            TenantAudit::create([
                'tenant_id' => $tenant->id,
                'object_id' => sprintf('00000000-0000-0000-0000-%012d', $i),
                'type' => 1,
                'diffs' => ['name' => 'Test '.$i],
                'transaction_hash' => 'hash'.$i,
                'blame_id' => 'user'.$i,
                'blame_user' => 'Test User '.$i,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $response = $this->get('/audit?entity_id='.$entity->id.'&page=2');

        $response->assertStatus(200);
        // Check that entity_id is in pagination links
        $response->assertSee('entity_id='.$entity->id);
    }

    public function test_user_cannot_filter_by_entity_they_dont_have_access_to(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();

        // Create entity that user has access to
        $accessibleEntity = Entity::create([
            'name' => 'Accessible Entity',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        // Create entity that user doesn't have access to
        $inaccessibleEntity = Entity::create([
            'name' => 'Inaccessible Entity',
            'model_class' => 'App\Models\UserAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        // Link only accessible entity
        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $accessibleEntity->id,
        ]);

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        // Try to filter by inaccessible entity
        $response = $this->get('/audit?entity_id='.$inaccessibleEntity->id);

        $response->assertStatus(200);
        // Should return empty results
        $response->assertDontSee('Inaccessible Entity');
    }
}
