<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenantEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_form(): void
    {
        $user = UserSystem::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user, 'user_system');
        $response->assertRedirect('/audit');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        UserSystem::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('user_system');
        $response->assertSessionHasErrors('email');
    }

    public function test_login_sets_current_tenant_in_session(): void
    {
        // Create user
        $user = UserSystem::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create work group
        $workGroup = WorkGroup::factory()->create();

        // Link user to work group
        $user->workGroups()->attach($workGroup->id);

        // Create tenant
        $tenant = Tenant::factory()->create();

        // Link work group to tenant
        WorkGroupTenantEntity::create([
            'work_group_id' => $workGroup->id,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user, 'user_system');
        $response->assertRedirect('/audit');
        $this->assertNotNull(session('current_tenant'));
        $this->assertEquals($tenant->id, session('current_tenant')->tenant->id);
    }

    public function test_authenticated_users_are_redirected_to_audit_from_login_page(): void
    {
        $user = UserSystem::factory()->create();

        $response = $this->actingAs($user, 'user_system')->get('/login');

        $response->assertRedirect('/audit');
    }

    public function test_users_can_logout(): void
    {
        $user = UserSystem::factory()->create();

        $response = $this->actingAs($user, 'user_system')->post('/logout');

        $this->assertGuest('user_system');
        $response->assertRedirect('/login');
        $this->assertNull(session('current_tenant'));
    }
}
