<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignRequestStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requests.assign') ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return ['assigned_user_id.exists' => 'Select an active user with request-processing permission.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $assignee = User::query()->find($this->integer('assigned_user_id'));
            if ($assignee && (! $assignee->is_active || ! $assignee->hasPermission('processing.manage'))) {
                $validator->errors()->add('assigned_user_id', 'Select an active user with request-processing permission.');
            }
        }];
    }
}
