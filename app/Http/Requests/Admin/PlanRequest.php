<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plan.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'type' => ['required', 'string', 'in:unlimited,session_pack'],
            // An unlimited plan has no session count by definition.
            'session_count' => ['nullable', 'required_if:type,session_pack', 'integer', 'min:1', 'max:99'],
            'price' => ['required', 'numeric', 'min:0', 'max:10000'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:730'],
            'included_service_ids' => ['present', 'array'],
            'included_service_ids.*' => ['integer', 'exists:services,id'],
            'perks' => ['present', 'array'],
            // Blank rows arrive as null via ConvertEmptyStringsToNull.
            'perks.*' => ['nullable', 'string', 'max:120'],
            'is_popular' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_count.required_if' => 'A session pack needs to say how many sessions it includes.',
        ];
    }
}
