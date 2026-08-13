<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            $this->route('service') ? 'service.update' : 'service.create'
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->route('service');

        $id = $service instanceof Service ? $service->id : null;

        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'description' => ['nullable', 'string', 'max:400'],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:60'],

            'requires_patch_test' => ['required', 'boolean'],
            'patch_test_lead_hours' => ['nullable', 'required_if:requires_patch_test,true', 'integer', 'min:1', 'max:336'],
            'is_skin_service' => ['required', 'boolean'],
            'is_house_call_eligible' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            // Price is per branch, so it is captured here for the branch the
            // manager is working in rather than stored on the service.
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'slug' => [
                'nullable', 'string', 'max:80',
                Rule::unique('services', 'slug')->ignore($id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patch_test_lead_hours.required_if' => 'Say how many hours before the appointment the patch test is needed.',
        ];
    }
}
