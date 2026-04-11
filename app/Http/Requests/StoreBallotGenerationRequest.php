<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBallotGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'election_id' => ['nullable', 'exists:elections,id'],
            'print_count' => ['required', 'integer', 'min:1', 'max:5000'],
            'per_sheet' => ['nullable', 'integer', 'in:1,2,4'],
            'scale_percent' => ['nullable', 'integer', 'min:40', 'max:100'],
        ];
    }
}
