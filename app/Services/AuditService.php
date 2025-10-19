<?php

namespace App\Services;

use App\DTOs\AuditFilterDTO;
use App\Models\Common\Auditable;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditService
{
    public function filter(AuditFilterDTO $filters)
    {
        $entityId = $filters->entity_id;
        if (! $entityId) {
            return new LengthAwarePaginator([], 0, 15);
        }
        // If entity_id is provided, validate and get the audit model
        $entity = Entity::find($entityId);

        if (! $entity || ! $entity->model_class) {
            // Return empty paginator if entity not found or has no model_class
            return new LengthAwarePaginator([], 0, 15);
        }

        // Check if the model_class exists and is a valid Model
        if (! class_exists($entity->model_class)) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $auditModel = new $entity->model_class();

        if (! in_array(Auditable::class, class_uses_recursive($auditModel))) {
            return new LengthAwarePaginator([], 0, 15);
        }

        // Query the specific audit model
        return $auditModel->filter($filters)->paginate($filters->per_page);
    }
}
