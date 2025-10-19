<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateTenantToUserRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function showLoginForm()
    {
        if (Auth::guard('user_system')->check()) {
            return redirect('/audit');
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        if ($this->authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember')
        )) {
            $request->session()->regenerate();

            return redirect()->intended('/audit');
        }

        throw ValidationException::withMessages([
            'email' => __('The provided credentials do not match our records.'),
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function updateTenant(UpdateTenantToUserRequest $request)
    {
        $user = Auth::guard('user_system')->user();
        $tenantId = $request->input('tenant_id');

        $this->authService->updateTenant($user, $tenantId);

        return back()->with('success', __('audit.tenant_updated'));
    }
}
