<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => ucwords(trim((string) $this->name)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:positions,name'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'votes_allowed' => ['required', 'integer', 'min:1', 'max:20'],
            'max_candidates_per_party' => ['required', 'integer', 'min:1', 'lte:votes_allowed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A position with this exact name already exists.',
            'max_candidates_per_party.lte' => 'The max candidates per party cannot exceed the total votes allowed.',
        ];
    }
}