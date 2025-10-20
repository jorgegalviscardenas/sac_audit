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

        if ($filters->from) {
            $query->where('created_at', '>=', $filters->from);
        }

        if ($filters->to) {
            $query->where('created_at', '<=', $filters->to);
        }

        if ($filters->audit_type) {
            $query->where('type', $filters->audit_type);
        }

        if ($filters->object_id) {
            $query->where('object_id', $filters->object_id);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function getDiffsHTML(): string
    {
        $old = $this->diffs['old'] ?? [];
        $new = $this->diffs['new'] ?? [];

        if (count($old) === 0) {
            $html = "{\n\"old\": null, \n\"new\": {\n";
        } else {
            $html = "{\n\"old\": ".json_encode($old, JSON_PRETTY_PRINT).", \n\"new\": {\n";
        }
        foreach ($new as $key => $value) {
            $oldValue = $old[$key] ?? null;
            if (! $oldValue || $oldValue != $value) {
                if (is_string($value)) {
                    $valueJSON = "\"<span style='background-color: #b3ffb3'>{$value}</span>\"";
                } else {
                    $valueJSON = "<span style='background-color: #b3ffb3'>".json_encode($value).'</span>';
                }
                $html .= "    \"{$key}\": $valueJSON,\n";
            } else {
                $html .= "    \"{$key}\": \"{$value}\",\n";
            }
        }

        return $html."}\n}";
    }
}
