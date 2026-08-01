<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class DecideRequestServiceRequest extends FormRequest {
 public function authorize(): bool { return $this->user() !== null; }
 public function rules(): array { return ['decision'=>['required',Rule::in(['approved','rejected'])],'decision_notes'=>['nullable','string','max:2000'],'discount_type'=>['nullable',Rule::in(['none','fixed','percentage'])],'discount_value'=>['nullable','numeric','min:0'],'discount_reason'=>['nullable',Rule::in(['regular_customer','family','special_discount','festival','management_approval','other'])],'government_charges'=>['nullable','array','max:20'],'government_charges.*.name'=>['required_with:government_charges','string','max:150'],'government_charges.*.amount'=>['required_with:government_charges','numeric','min:0','max:99999999.99'],'government_charges.*.note'=>['nullable','string','max:500']]; }
}
