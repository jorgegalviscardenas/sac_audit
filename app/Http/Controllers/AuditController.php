<?php

namespace App\Http\Controllers;

use App\DTOs\AuditFilterDTO;
use App\Http\Requests\AuditFilterRequest;
use App\Models\AuditType;
use App\Models\Entity;
use App\Services\AuditService;
use App\Services\UserSystemTenantService;
use Illuminate\Support\Facades\Auth;

class AuditController extends Controller
{
    public function __construct(
        private readonly UserSystemTenantService $userSystemTenantService,
        private readonly AuditService $auditService
    ) {}

    public function index(AuditFilterRequest $request)
    {
        $userId = (string) Auth::guard('user_system')->id();
        $tenants = $this->userSystemTenantService->getTenants($userId);
        $currentTenant = session('current_tenant');
        $auditTypes = AuditType::all();

        $audits = null;
        $entity = null;
        if ($currentTenant) {
            $validated = $request->validated();

            $entityId = $validated['entity_id'] ?? null;

            // Validate that the entity_id belongs to the user's accessible entities
            if ($entityId) {
                $accessibleEntityIds = collect($currentTenant->entities)->pluck('entity.id')->toArray();
                if (! in_array($entityId, $accessibleEntityIds)) {
                    $entityId = null; // User doesn't have access to this entity
                }
            }

            $filters = new AuditFilterDTO(
                tenant_id: $currentTenant->tenant->id,
                entity_id: $entityId,
                from: $validated['from'] ?? null,
                to: $validated['to'] ?? null,
                audit_type: $validated['audit_type'] ?? null,
                object_id: $validated['object_id'] ?? null,
                page: (int) ($request->input('page', 1)),
                per_page: 15
            );

            if ($entityId) {
                $entity = Entity::find($entityId);
            }

            $audits = $this->auditService->filter($filters);
        }

        return view('audit.index', compact('tenants', 'currentTenant', 'audits', 'entity', 'auditTypes'));
    }
}
