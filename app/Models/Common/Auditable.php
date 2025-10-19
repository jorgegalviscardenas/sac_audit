<?php

namespace App\Models\Common;

use App\DTOs\AuditFilterDTO;
use Illuminate\Database\Eloquent\Builder;

trait Auditable
{
    public function filter(AuditFilterDTO $filters): Builder
    {
        $query = static::query();

        if ($filters->tenant_id) {
            $query->where('tenant_id', $filters->tenant_id);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
