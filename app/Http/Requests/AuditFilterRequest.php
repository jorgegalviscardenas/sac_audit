<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entity_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'audit_type' => ['nullable', 'integer', 'in:1,2,3'],
            'object_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'entity_id.required' => __('audit.entity_required'),
        ];
    }
}
