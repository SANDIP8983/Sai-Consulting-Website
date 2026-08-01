<?php
namespace App\Http\Requests\Admin;
class UpdateRequestFileInformationRequest extends OpenRequestFileRequest
{
    public function rules(): array
    {
        $rules = parent::rules(); unset($rules['customer_remark']);
        $rules['actual_completion_date'] = ['nullable', 'date', 'after_or_equal:file_opened_at'];
        $rules['estimated_completion_date'] = ['nullable', 'date'];
        return $rules;
    }
}
