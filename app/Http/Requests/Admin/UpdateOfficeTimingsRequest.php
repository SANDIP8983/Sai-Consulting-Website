<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOfficeTimingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timings' => ['required', 'array', 'size:7'],
            'timings.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'timings.*.opens_at' => ['nullable', 'date_format:H:i'],
            'timings.*.closes_at' => ['nullable', 'date_format:H:i'],
            'timings.*.is_closed' => ['required', 'boolean'],
            'timings.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('timings', []) as $index => $timing) {
                if (filter_var($timing['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                if (empty($timing['opens_at']) || empty($timing['closes_at'])) {
                    $validator->errors()->add("timings.{$index}.opens_at", 'Opening and closing times are required for an open day.');

                    continue;
                }

                if ($timing['closes_at'] <= $timing['opens_at']) {
                    $validator->errors()->add("timings.{$index}.closes_at", 'Closing time must be later than opening time.');
                }
            }
        }];
    }
}
