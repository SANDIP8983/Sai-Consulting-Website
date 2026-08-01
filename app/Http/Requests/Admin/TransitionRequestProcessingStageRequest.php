<?php
namespace App\Http\Requests\Admin;
use App\Services\FileDocumentProcessingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class TransitionRequestProcessingStageRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['processing_stage' => ['required', Rule::in(FileDocumentProcessingService::STAGES)], 'remarks' => ['nullable', 'string', 'max:2000'], 'is_visible_to_customer' => ['nullable', 'boolean']]; }
}
