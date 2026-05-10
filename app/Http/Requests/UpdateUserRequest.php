<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('facilitator') ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user?->id)],
            'gender' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:50'],
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A facilitator with this name already exists.',
        ];
    }
}