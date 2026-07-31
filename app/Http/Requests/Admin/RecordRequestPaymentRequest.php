<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RecordRequestPaymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        $customerRequest = $this->route('customerRequest');
        $methods = $customerRequest?->isOffline() ? ['upi', 'bank_transfer', 'cheque', 'cash', 'other'] : ['upi', 'bank_transfer', 'other'];

        return ['amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'], 'payment_status' => ['required', Rule::in(['pending', 'received', 'failed', 'refunded'])], 'payment_method' => ['required', Rule::in($methods)], 'transaction_reference' => ['nullable', 'string', 'max:150'], 'received_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'], 'customer_remark' => ['nullable', 'string', 'max:2000']];
    }
}
