<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
class UpdateRequestDraftingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['draft_started_at' => ['nullable', 'date'], 'draft_ready_at' => ['nullable', 'date', 'after_or_equal:draft_started_at'], 'customer_verification_at' => ['nullable', 'date'], 'correction_note' => ['nullable', 'string', 'max:5000'], 'final_draft_at' => ['nullable', 'date'], 'drafting_internal_note' => ['nullable', 'string', 'max:5000'], 'drafting_customer_remark' => ['nullable', 'string', 'max:2000']]; }
}
