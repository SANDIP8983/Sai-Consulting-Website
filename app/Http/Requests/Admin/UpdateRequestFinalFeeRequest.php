<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestFinalFeeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['final_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99']]; }
}
