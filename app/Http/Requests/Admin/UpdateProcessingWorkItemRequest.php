<?php
namespace App\Http\Requests\Admin;
use App\Models\RequestServiceWorkScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateProcessingWorkItemRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['status'=>['required',Rule::in(RequestServiceWorkScope::STATUSES)],'reason'=>['nullable','string','max:2000'],'internal_note'=>['nullable','string','max:2000'],'customer_remark'=>['nullable','string','max:2000']]; }
}
