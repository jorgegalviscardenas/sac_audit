<?php

namespace App\DTOs;

use App\Models\Entity;

class EntityConfigDTO
{
    public function __construct(
        public readonly Entity $entity,
        public readonly array $fields,
    ) {}
}
