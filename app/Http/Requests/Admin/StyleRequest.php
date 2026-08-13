<?php

namespace App\Http\Requests\Admin;

use App\Models\Style;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('service.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $style = $this->route('style');

        $id = $style instanceof Style ? $style->id : null;

        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            // The spoken reference. Kept short because a client reads it aloud.
            'code' => [
                'required', 'string', 'max:4',
                Rule::unique('styles', 'code')->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:400'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'gender_tag' => ['nullable', 'string', 'in:men,women,unisex,kids'],
            'hair_type_tag' => ['present', 'array'],
            'hair_type_tag.*' => ['nullable', 'string', 'max:30'],
            'typical_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'is_featured' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],

            // Our own photos, so the gallery is not stock imagery.
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:6144'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.max' => 'That photo is over 6MB. Phone shots usually are, so it will be resized on upload.',
            'code.unique' => 'Another style already uses that number.',
        ];
    }
}
