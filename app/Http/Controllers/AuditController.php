<?php

namespace App\Http\Controllers;

use App\Services\UserSystemTenantService;
use Illuminate\Support\Facades\Auth;

class AuditController extends Controller
{
    public function __construct(
        private readonly UserSystemTenantService $userSystemTenantService
    ) {}

    public function index()
    {
        $userId = (string) Auth::guard('user_system')->id();
        $tenants = $this->userSystemTenantService->getTenants($userId);
        $currentTenant = session('current_tenant');

        return view('audit.index', compact('tenants', 'currentTenant'));
    }
}
