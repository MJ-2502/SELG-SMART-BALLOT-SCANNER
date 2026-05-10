<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Added unique validation to name to prevent duplicates
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'gender' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A facilitator with this name already exists.',
            'username.unique' => 'This username is already taken.',
        ];
    }
}