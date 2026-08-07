<?php

namespace App\Http\Requests\Admin;

class UpdateRequestDispatchRequest extends StoreRequestDispatchRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['dispatch_status'], $rules['proof'], $rules['proof_type']);

        return $rules;
    }
}
