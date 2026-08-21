<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['customer_name' => ['required', 'string', 'max:120'], 'mobile' => ['required', 'regex:/^[6-9][0-9]{9}$/'], 'whatsapp' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'], 'email' => ['nullable', 'email:rfc', 'max:255'], 'service_id' => ['required', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)->where('show_on_public_website', true))], 'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now('Asia/Kolkata')->addMonths(6)->toDateString()], 'appointment_time' => ['required', 'date_format:H:i'], 'customer_note' => ['nullable', 'string', 'max:1000'], 'admin_note' => ['nullable', 'string', 'max:1000'], 'declaration' => ['required', 'accepted']];
    }
}
