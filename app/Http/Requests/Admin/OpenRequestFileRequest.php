<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class OpenRequestFileRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['file_opened_at' => ['required', 'date'], 'priority' => ['required', Rule::in(['normal', 'urgent', 'high'])], 'file_in_charge_user_id' => ['nullable', 'integer', 'exists:users,id'], 'internal_file_note' => ['nullable', 'string', 'max:5000'], 'customer_remark' => ['nullable', 'string', 'max:2000']]; }
}
