<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartylistCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $normalizedParty = strtolower(trim((string) $this->input('party', '')));

        return [
            'party' => ['required', 'string', 'max:255'],
            'color_code' => [
                'required',
                'regex:/^#[0-9A-F]{6}$/',
                Rule::in(config('candidate_colors.palette', [])),
                function (string $attribute, mixed $value, \Closure $fail) use ($normalizedParty): void {
                    $message = $this->validateColorOwnership((string) $value, $normalizedParty);

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
            'entries' => ['required', 'array'],
            'entries.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'party' => trim((string) $this->input('party', '')),
            'color_code' => strtoupper(trim((string) $this->input('color_code', ''))),
        ]);
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

    private function validateColorOwnership(string $colorCode, string $normalizedParty): ?string
    {
        $partyColors = Candidate::query()
            ->whereNotNull('party')
            ->whereRaw('LOWER(TRIM(party)) = ?', [$normalizedParty])
            ->whereNotNull('color_code')
            ->selectRaw('UPPER(color_code) as color_code')
            ->distinct()
            ->pluck('color_code');

        if ($partyColors->isNotEmpty() && !$partyColors->contains($colorCode)) {
            $partyLabel = trim((string) $this->input('party', 'this partylist'));

            return "Partylist \"{$partyLabel}\" already uses {$partyColors->first()}.";
        }

        $hasConflict = Candidate::query()
            ->whereRaw('UPPER(color_code) = ?', [$colorCode])
            ->where(function ($query) use ($normalizedParty): void {
                $query
                    ->whereNull('party')
                    ->orWhereRaw('LOWER(TRIM(party)) <> ?', [$normalizedParty]);
            })
            ->exists();

        if ($hasConflict) {
            return 'This color is already assigned to another partylist or independent candidate.';
        }

        return null;
    }
}
