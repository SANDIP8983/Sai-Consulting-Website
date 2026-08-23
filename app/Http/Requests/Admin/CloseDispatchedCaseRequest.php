<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ConvertsLocalDateTimes;
use App\Support\IndiaDateTime;
use Illuminate\Foundation\Http\FormRequest;

class CloseDispatchedCaseRequest extends FormRequest
{
    use ConvertsLocalDateTimes;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['closure_date' => ['required', 'date', IndiaDateTime::notFutureRule()], 'customer_remark' => ['nullable', 'string', 'max:2000'], 'internal_note' => ['nullable', 'string', 'max:2000'], 'confirmed' => ['accepted'], 'revenue_entry_override' => ['nullable', 'boolean'], 'revenue_entry_override_reason' => ['nullable', 'string', 'max:2000', 'required_if:revenue_entry_override,1']];
    }

    protected function localDateTimeFields(): array
    {
        return ['closure_date'];
    }
}
