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

class TenantSelectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_index_displays_tenant_selector(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant = Tenant::factory()->create();
        $entity = Entity::create(['name' => 'Test Entity', 'model_class' => 'App\Models\TenantAudit']);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        $response = $this->actingAs($user, 'user_system')->get('/audit');

        $response->assertStatus(200);
        // Verify that the Select2 tenant selector is present
        $response->assertSee('select2-tenant', false);
        $response->assertSee('tenantSelect', false);
    }

    public function test_user_can_update_current_tenant(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);
        $entity = Entity::create(['name' => 'Test Entity', 'model_class' => 'App\Models\TenantAudit']);

        $workGroupTenant1 = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant1->id,
        ]);

        $workGroupTenant2 = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant2->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant1->id,
            'entity_id' => $entity->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant2->id,
            'entity_id' => $entity->id,
        ]);

        $response = $this->actingAs($user, 'user_system')->post('/update-tenant', [
            'tenant_id' => $tenant2->id,
        ]);

        $response->assertRedirect();
        $this->assertNotNull(session('current_tenant'));
        $this->assertEquals($tenant2->id, session('current_tenant')->tenant->id);
    }

    public function test_user_cannot_update_to_tenant_without_access(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $accessibleTenant = Tenant::factory()->create();
        $inaccessibleTenant = Tenant::factory()->create();
        $entity = Entity::create(['name' => 'Test Entity', 'model_class' => 'App\Models\TenantAudit']);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $accessibleTenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        $response = $this->actingAs($user, 'user_system')->post('/update-tenant', [
            'tenant_id' => $inaccessibleTenant->id,
        ]);

        $response->assertRedirect();
        $this->assertNull(session('current_tenant'));
    }

    public function test_audit_index_shows_only_accessible_tenants(): void
    {
        $user = UserSystem::factory()->create();
        $workGroup = WorkGroup::factory()->create();
        $user->workGroups()->attach($workGroup->id);

        $accessibleTenant = Tenant::factory()->create(['name' => 'Accessible Tenant']);
        $inaccessibleTenant = Tenant::factory()->create(['name' => 'Inaccessible Tenant']);
        $entity = Entity::create(['name' => 'Test Entity', 'model_class' => 'App\Models\TenantAudit']);

        $workGroupTenant = WorkGroupTenant::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $accessibleTenant->id,
        ]);

        WorkGroupTenantEntity::create([
            'work_group_tenant_id' => $workGroupTenant->id,
            'entity_id' => $entity->id,
        ]);

        // Test the tenant API endpoint instead of HTML rendering
        $response = $this->actingAs($user, 'user_system')->get('/user-on-session/tenants');

        $response->assertStatus(200);
        $response->assertJsonFragment(['text' => 'Accessible Tenant']);
        $response->assertJsonMissing(['text' => 'Inaccessible Tenant']);
    }

    public function test_guest_cannot_access_audit_page(): void
    {
        $response = $this->get('/audit');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_update_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->post('/update-tenant', [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertRedirect('/login');
    }
}
