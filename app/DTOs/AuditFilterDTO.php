<?php

namespace App\DTOs;

class AuditFilterDTO
{
    public function __construct(
        public readonly string $tenant_id,
        public readonly ?string $entity_id = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $audit_type = null,
        public readonly ?string $object_id = null,
        public readonly int $page = 1,
        public readonly int $per_page = 15,
    ) {}
}
