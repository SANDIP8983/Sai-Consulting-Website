<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => str($this->input('username'))->trim()->lower()->toString(),
            'email' => filled($this->input('email')) ? str($this->input('email'))->trim()->lower()->toString() : null,
            'mobile' => filled($this->input('mobile')) ? preg_replace('/\s+/', '', trim((string) $this->input('mobile'))) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/\A[a-z0-9._-]+\z/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'regex:/\A[6-9][0-9]{9}\z/', 'unique:users,mobile'],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['required', 'boolean'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'Please enter the staff member’s 10-digit mobile number.',
            'mobile.regex' => 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.',
            'mobile.unique' => 'This mobile number is already assigned to another user.',
        ];
    }
}
