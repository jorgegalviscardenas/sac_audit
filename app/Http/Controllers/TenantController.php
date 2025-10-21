<?php

namespace App\Http\Controllers;

use App\Services\UserSystemTenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    public function __construct(
        private readonly UserSystemTenantService $userSystemTenantService
    ) {}

    /**
     * Get tenants available for the authenticated user with search and pagination support.
     * Compatible with Select2 AJAX requests.
     */
    public function listByUser(Request $request): JsonResponse
    {
        $userId = (string) Auth::guard('user_system')->id();
        $search = $request->input('search') ?: null;
        $page = (int) $request->input('page', 1);
        $perPage = 10;

        // Get paginated tenants using the service
        $result = $this->userSystemTenantService->getPaginatedTenants($userId, $search, $page, $perPage);

        // Format response for Select2
        return response()->json([
            'results' => collect($result['data'])->map(function ($tenant) {
                return [
                    'id' => $tenant['id'],
                    'text' => $tenant['name'],
                ];
            })->toArray(),
            'pagination' => [
                'more' => $result['has_more'],
            ],
        ]);
    }
}
