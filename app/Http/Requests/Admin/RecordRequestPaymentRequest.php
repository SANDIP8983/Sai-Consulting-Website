<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RecordRequestPaymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['amount' => ['required', 'numeric', 'gt:0'], 'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'other'])], 'transaction_reference' => ['nullable', 'string', 'max:150'], 'received_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]; }
}
