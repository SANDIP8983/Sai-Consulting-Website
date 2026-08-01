<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\StoreCustomerRequestRequest;

class StoreOfflineCustomerRequestRequest extends StoreCustomerRequestRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['declaration']);

        return $rules;
    }
}
