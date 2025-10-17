<?php

namespace Tests\Feature;

use App\Models\UserSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'audit']);
    }

    public function test_login_page_displays_correctly(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        // Create a test user
        $user = UserSystem::create([
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Attempt to login
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert redirect to dashboard
        $response->assertRedirect('/dashboard');

        // Assert user is authenticated
        $this->assertAuthenticatedAs($user, 'user_system');
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        // Create a test user
        UserSystem::create([
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Attempt to login with wrong password
        $response = $this->from('/login')->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        // Assert redirected back to login
        $response->assertRedirect('/login');

        // Assert session has errors
        $response->assertSessionHasErrors('email');

        // Assert user is not authenticated
        $this->assertGuest('user_system');
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        // Attempt to login with non-existent email
        $response = $this->from('/login')->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        // Assert redirected back to login
        $response->assertRedirect('/login');

        // Assert session has errors
        $response->assertSessionHasErrors('email');

        // Assert user is not authenticated
        $this->assertGuest('user_system');
    }

    public function test_validation_error_when_email_is_missing(): void
    {
        $response = $this->post('/login', [
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_validation_error_when_password_is_missing(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        // Create and authenticate a user
        $user = UserSystem::create([
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user, 'user_system');

        // Try to access login page
        $response = $this->get('/login');

        // Should redirect to dashboard
        $response->assertRedirect('/dashboard');
    }

    public function test_user_can_logout(): void
    {
        // Create and authenticate a user
        $user = UserSystem::create([
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user, 'user_system');

        // Logout
        $response = $this->post('/logout');

        // Assert redirected to login
        $response->assertRedirect('/login');

        // Assert user is no longer authenticated
        $this->assertGuest('user_system');
    }
}
