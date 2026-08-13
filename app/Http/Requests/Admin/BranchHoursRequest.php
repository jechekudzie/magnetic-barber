<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BranchHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branch.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i'],
            'days_open' => ['present', 'array'],
            'days_open.*' => ['integer', 'between:0,6'],

            'house_call_enabled' => ['required', 'boolean'],
            'house_call_opens_at' => ['nullable', 'date_format:H:i'],
            'house_call_closes_at' => ['nullable', 'date_format:H:i'],
            'house_call_days_open' => ['present', 'array'],
            'house_call_days_open.*' => ['integer', 'between:0,6'],
            'house_call_radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'house_call_fee' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * A window that closes before it opens would silently produce an empty
     * calendar, which reads as a broken booking page rather than a bad setting.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled(['opens_at', 'closes_at'])
                && $this->string('closes_at')->value() <= $this->string('opens_at')->value()) {
                $validator->errors()->add('closes_at', 'Closing time must be after opening time.');
            }

            $houseOpens = $this->input('house_call_opens_at');
            $houseCloses = $this->input('house_call_closes_at');

            if ($houseOpens !== null && $houseCloses !== null && $houseCloses <= $houseOpens) {
                $validator->errors()->add(
                    'house_call_closes_at',
                    'House call closing time must be after its opening time.'
                );
            }

            if ($houseOpens !== null && $houseOpens < $this->input('opens_at')) {
                $validator->errors()->add(
                    'house_call_opens_at',
                    'House calls cannot start before the shop opens.'
                );
            }

            if ($houseCloses !== null && $houseCloses > $this->input('closes_at')) {
                $validator->errors()->add(
                    'house_call_closes_at',
                    'House calls cannot run past closing time.'
                );
            }
        });
    }
}
