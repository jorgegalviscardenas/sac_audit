<?php

namespace App\Services;

use App\DTOs\CurrentTenantDTO;
use App\Models\UserSystem;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function __construct(
        private readonly UserSystemTenantService $userSystemTenantService
    ) {}

    /**
     * Attempt to authenticate a user with user_system guard.
     */
    public function login(string $email, string $password, bool $remember = false): bool
    {
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        if (Auth::guard('user_system')->attempt($credentials, $remember)) {
            $user = Auth::guard('user_system')->user();

            // Get first tenant for the user
            $currentTenant = $this->userSystemTenantService->getFirstTenant((string) $user->id);

            if ($currentTenant) {
                $this->setTenantOnSession($user, $currentTenant);
            }

            return true;
        }

        return false;
    }

    /**
     * Logout the authenticated user.
     */
    public function logout(): void
    {
        session()->forget('current_tenant');
        Auth::guard('user_system')->logout();
    }

    /**
     * Update the current tenant for a user.
     */
    public function updateTenant(UserSystem $user, string $tenantId): UserSystem
    {
        $currentTenant = $this->userSystemTenantService->getTenantWithEntities((string) $user->id, $tenantId);

        if ($currentTenant) {
            $this->setTenantOnSession($user, $currentTenant);
        }

        return $user;
    }

    /**
     * Set the current tenant on user and session.
     */
    private function setTenantOnSession(UserSystem $user, CurrentTenantDTO $currentTenant): void
    {
        $user->currentTenant = $currentTenant;
        session()->put('current_tenant', $currentTenant);
    }
}
