<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $trackedMethods = ['india_post_registered', 'india_post_speed_post', 'courier'];

        return [
            'dispatch_status' => ['required', Rule::in(['not_dispatched', 'dispatched', 'delivered'])],
            'dispatch_method' => ['required', Rule::in(['office_collection', 'hand_delivery', 'india_post_registered', 'india_post_speed_post', 'courier', 'email', 'whatsapp', 'other'])],
            'dispatch_date' => ['required', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => in_array($this->input('dispatch_method'), $trackedMethods, true))],
            'carrier_name' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => $this->input('dispatch_method') === 'courier')],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'customer_remark' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
