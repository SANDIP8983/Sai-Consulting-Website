<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class BulkProcessingWorkItemRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['action'=>['required',Rule::in(['start_selected','complete_selected','not_required','cancel_selected','save_notes'])],'work_scope_ids'=>['required','array','min:1'],'work_scope_ids.*'=>['integer','distinct','exists:request_service_work_scopes,id'],'reason'=>['nullable','string','max:2000'],'internal_note'=>['nullable','string','max:2000'],'customer_remark'=>['nullable','string','max:2000']]; }
}
