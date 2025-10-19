<?php

namespace Tests\Unit\Services;

use App\DTOs\CurrentTenantDTO;
use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenantEntity;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    public function test_login_returns_false_with_invalid_credentials(): void
    {
        $result = $this->authService->login('invalid@example.com', 'wrongpassword');

        $this->assertFalse($result);
    }

    public function test_login_returns_true_with_valid_credentials(): void
    {
        $user = UserSystem::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $result = $this->authService->login('test@example.com', 'password');

        $this->assertTrue($result);
        $this->assertAuthenticatedAs($user, 'user_system');
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

        $result = $this->authService->login('test@example.com', 'password');

        $this->assertTrue($result);
        $this->assertNotNull(session('current_tenant'));
        $this->assertInstanceOf(CurrentTenantDTO::class, session('current_tenant'));
        $this->assertEquals($tenant->id, session('current_tenant')->tenant->id);
    }

    public function test_logout_clears_current_tenant_from_session(): void
    {
        // Set up session
        session(['current_tenant' => new CurrentTenantDTO(Tenant::factory()->create())]);

        // Login a user first
        $user = UserSystem::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
        Auth::guard('user_system')->login($user);

        $this->authService->logout();

        $this->assertNull(session('current_tenant'));
        $this->assertGuest('user_system');
    }

    public function test_update_tenant_updates_session(): void
    {
        // Create user
        $user = UserSystem::factory()->create();

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

        $this->authService->updateTenant($user, $tenant->id);

        $this->assertNotNull(session('current_tenant'));
        $this->assertEquals($tenant->id, session('current_tenant')->tenant->id);
        $this->assertEquals($tenant->id, $user->currentTenant->tenant->id);
    }

    public function test_update_tenant_does_not_update_if_user_has_no_access(): void
    {
        $user = UserSystem::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->authService->updateTenant($user, $tenant->id);

        $this->assertNull(session('current_tenant'));
        $this->assertNull($user->currentTenant);
    }
}
