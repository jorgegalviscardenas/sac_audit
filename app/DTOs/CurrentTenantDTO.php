<?php

namespace App\DTOs;

use App\Models\Tenant;

class CurrentTenantDTO
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $entities,
    ) {}
}
