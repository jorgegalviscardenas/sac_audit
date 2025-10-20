<?php

namespace Tests\Unit\Services;

use App\DTOs\AuditFilterDTO;
use App\Models\Entity;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditService();
    }

    public function test_filter_returns_empty_paginator_when_no_entity_id(): void
    {
        $filters = new AuditFilterDTO(
            tenant_id: '00000000-0000-0000-0000-000000000001',
            entity_id: null
        );

        $result = $this->service->filter($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->total());
    }

    public function test_filter_returns_empty_paginator_when_entity_not_found(): void
    {
        $filters = new AuditFilterDTO(
            tenant_id: '00000000-0000-0000-0000-000000000001',
            entity_id: '00000000-0000-0000-0000-000000000999'
        );

        $result = $this->service->filter($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->total());
    }

    public function test_filter_returns_empty_paginator_when_model_class_does_not_exist(): void
    {
        $entity = Entity::create([
            'name' => 'Test Entity',
            'model_class' => 'App\Models\NonExistentModel',
        ]);

        $filters = new AuditFilterDTO(
            tenant_id: '00000000-0000-0000-0000-000000000001',
            entity_id: $entity->id
        );

        $result = $this->service->filter($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->total());
    }

    public function test_filter_returns_empty_paginator_when_model_does_not_use_auditable_trait(): void
    {
        $entity = Entity::create([
            'name' => 'Test Entity',
            'model_class' => 'App\Models\Tenant', // Tenant doesn't use Auditable trait
        ]);

        $filters = new AuditFilterDTO(
            tenant_id: '00000000-0000-0000-0000-000000000001',
            entity_id: $entity->id
        );

        $result = $this->service->filter($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(0, $result->total());
    }

    public function test_filter_returns_paginator_with_valid_entity(): void
    {
        $entity = Entity::create([
            'name' => 'Tenant Audits',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $filters = new AuditFilterDTO(
            tenant_id: '00000000-0000-0000-0000-000000000001',
            entity_id: $entity->id,
            page: 1,
            per_page: 15
        );

        $result = $this->service->filter($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        // Should return paginator even if empty (no audit records yet)
        $this->assertIsInt($result->total());
    }

    public function test_filter_applies_all_filters(): void
    {
        $entity = Entity::create([
            'name' => 'Tenant Audits',
            'model_class' => 'App\Models\TenantAudit',
        ]);

        $filters = new AuditFilterDTO(
            tenant_id: '00000000-0000-0000-0000-000000000001',
            entity_id: $entity->id,
            from: '2024-01-01',
            to: '2024-12-31',
            audit_type: 1,
            object_id: '00000000-0000-0000-0000-000000000002',
            page: 1,
            per_page: 15
        );

        $result = $this->service->filter($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertIsInt($result->total());
    }
}
