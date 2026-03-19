<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartylistCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'entries' => ['required', 'array'],
            'entries.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $entries = collect((array) $this->input('entries', []))
                ->map(fn ($name) => trim((string) $name))
                ->filter(fn ($name) => $name !== '');

            if ($entries->isEmpty()) {
                $validator->errors()->add('entries', 'Add at least one candidate name for this partylist.');
            }
        });
    }
}
