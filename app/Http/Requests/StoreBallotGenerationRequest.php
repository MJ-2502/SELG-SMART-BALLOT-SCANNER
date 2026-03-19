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
            'election_id' => ['required', 'exists:elections,id'],
            'print_count' => ['required', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
