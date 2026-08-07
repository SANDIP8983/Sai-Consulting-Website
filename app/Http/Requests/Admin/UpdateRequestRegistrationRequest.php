<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['token_booking_status' => ['nullable', Rule::in(['not_required', 'pending', 'booked'])], 'token_number' => ['nullable', 'string', 'max:100'], 'token_scheduled_at' => ['nullable', 'date'], 'sub_registrar_office_name' => ['nullable', 'string', 'max:200'], 'registration_appointment_at' => ['nullable', 'date'], 'registration_date' => ['nullable', 'date'], 'registration_number' => ['nullable', 'string', 'max:150'], 'registration_number_public' => ['nullable', 'boolean'], 'registration_internal_note' => ['nullable', 'string', 'max:5000'], 'registration_customer_remark' => ['nullable', 'string', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['registration_number_public' => $this->boolean('registration_number_public')]);
    }
}
