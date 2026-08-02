<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestDispatchRequest extends StoreRequestDispatchRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['dispatch_status'], $rules['proof'], $rules['proof_type']);
        return $rules;
    }
}
