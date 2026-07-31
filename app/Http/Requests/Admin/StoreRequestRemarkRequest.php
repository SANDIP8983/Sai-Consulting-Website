<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
class StoreRequestRemarkRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['remarks' => ['required', 'string', 'max:2000'], 'is_visible_to_customer' => ['required', 'boolean']]; }
}
