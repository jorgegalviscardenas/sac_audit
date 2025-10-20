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

    public function test_user_can_filter_audits_by_date_range(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();
        $entity = Entity::create([
            'name' => 'Tenants',
            'model_class' => 'App\\Models\\TenantAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        // Create audit records with different dates
        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000001',
            'type' => 1,
            'diffs' => ['name' => 'Old Record'],
            'transaction_hash' => 'hash1',
            'blame_id' => 'user1',
            'blame_user' => 'Old User',
            'created_at' => now()->subDays(10),
        ]);

        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000002',
            'type' => 1,
            'diffs' => ['name' => 'Recent Record'],
            'transaction_hash' => 'hash2',
            'blame_id' => 'user2',
            'blame_user' => 'Recent User',
            'created_at' => now()->subDays(2),
        ]);

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $fromDate = now()->subDays(5)->format('Y-m-d');
        $toDate = now()->format('Y-m-d');

        $response = $this->get('/audit?entity_id='.$entity->id.'&from='.$fromDate.'&to='.$toDate);

        $response->assertStatus(200);
        $response->assertSee('Recent User');
        $response->assertDontSee('Old User');
    }

    public function test_user_can_filter_audits_by_type(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();
        $entity = Entity::create([
            'name' => 'Tenants',
            'model_class' => 'App\\Models\\TenantAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        // Create audit records with different types
        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000001',
            'type' => 1, // Created
            'diffs' => ['name' => 'Created Record'],
            'transaction_hash' => 'hash1',
            'blame_id' => 'user1',
            'blame_user' => 'Create User',
            'created_at' => now(),
        ]);

        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000002',
            'type' => 2, // Updated
            'diffs' => ['name' => 'Updated Record'],
            'transaction_hash' => 'hash2',
            'blame_id' => 'user2',
            'blame_user' => 'Update User',
            'created_at' => now(),
        ]);

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $response = $this->get('/audit?entity_id='.$entity->id.'&audit_type=1');

        $response->assertStatus(200);
        $response->assertSee('Create User');
        $response->assertDontSee('Update User');
    }

    public function test_user_can_filter_audits_by_object_id(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();
        $entity = Entity::create([
            'name' => 'Tenants',
            'model_class' => 'App\\Models\\TenantAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        $targetObjectId = '00000000-0000-0000-0000-000000000001';

        // Create audit records with different object IDs
        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => $targetObjectId,
            'type' => 1,
            'diffs' => ['name' => 'Target Record'],
            'transaction_hash' => 'hash1',
            'blame_id' => 'user1',
            'blame_user' => 'Target User',
            'created_at' => now(),
        ]);

        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000002',
            'type' => 1,
            'diffs' => ['name' => 'Other Record'],
            'transaction_hash' => 'hash2',
            'blame_id' => 'user2',
            'blame_user' => 'Other User',
            'created_at' => now(),
        ]);

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $response = $this->get('/audit?entity_id='.$entity->id.'&object_id='.$targetObjectId);

        $response->assertStatus(200);
        $response->assertSee('Target User');
        $response->assertDontSee('Other User');
    }

    public function test_user_can_combine_multiple_filters(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();
        $entity = Entity::create([
            'name' => 'Tenants',
            'model_class' => 'App\\Models\\TenantAudit',
        ]);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        $targetObjectId = '00000000-0000-0000-0000-000000000001';

        // Create various audit records
        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => $targetObjectId,
            'type' => 1,
            'diffs' => ['name' => 'Match All'],
            'transaction_hash' => 'hash1',
            'blame_id' => 'user1',
            'blame_user' => 'Match User',
            'created_at' => now()->subDays(2),
        ]);

        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => $targetObjectId,
            'type' => 2,
            'diffs' => ['name' => 'Wrong Type'],
            'transaction_hash' => 'hash2',
            'blame_id' => 'user2',
            'blame_user' => 'Wrong Type User',
            'created_at' => now()->subDays(2),
        ]);

        TenantAudit::create([
            'tenant_id' => $tenant->id,
            'object_id' => '00000000-0000-0000-0000-000000000002',
            'type' => 1,
            'diffs' => ['name' => 'Wrong Object'],
            'transaction_hash' => 'hash3',
            'blame_id' => 'user3',
            'blame_user' => 'Wrong Object User',
            'created_at' => now()->subDays(2),
        ]);

        // Set current tenant in session
        $this->actingAs($user, 'user_system')
            ->post('/update-tenant', ['tenant_id' => $tenant->id]);

        $fromDate = now()->subDays(5)->format('Y-m-d');
        $toDate = now()->format('Y-m-d');

        $response = $this->get('/audit?entity_id='.$entity->id.'&from='.$fromDate.'&to='.$toDate.'&audit_type=1&object_id='.$targetObjectId);

        $response->assertStatus(200);
        $response->assertSee('Match User');
        $response->assertDontSee('Wrong Type User');
        $response->assertDontSee('Wrong Object User');
    }
}
