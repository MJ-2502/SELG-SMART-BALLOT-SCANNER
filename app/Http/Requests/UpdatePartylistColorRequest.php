<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartylistColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $normalizedParty = $this->normalizedParty();

        return [
            'party' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalizedParty = $this->normalizedParty();

                    $exists = Candidate::query()
                        ->when(
                            $normalizedParty === null,
                            fn ($query) => $query->whereNull('party'),
                            fn ($query) => $query
                                ->whereNotNull('party')
                                ->whereRaw('LOWER(TRIM(party)) = ?', [$normalizedParty])
                        )
                        ->exists();

                    if (!$exists) {
                        $fail('Group not found or already deleted.');
                    }
                },
            ],
            'color_code' => [
                'required',
                'regex:/^#[0-9A-F]{6}$/',
                Rule::in(config('candidate_colors.palette', [])),
                function (string $attribute, mixed $value, \Closure $fail) use ($normalizedParty): void {
                    $hasConflict = Candidate::query()
                        ->whereRaw('UPPER(color_code) = ?', [(string) $value])
                        ->when(
                            $normalizedParty === null,
                            fn ($query) => $query->whereNotNull('party'),
                            fn ($query) => $query->where(function ($innerQuery) use ($normalizedParty): void {
                                $innerQuery
                                    ->whereNull('party')
                                    ->orWhereRaw('LOWER(TRIM(party)) <> ?', [$normalizedParty]);
                            })
                        )
                        ->exists();

                    if ($hasConflict) {
                        $fail('This color is already assigned to another partylist or independent candidate.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'party' => trim((string) $this->input('party', '')),
            'color_code' => strtoupper(trim((string) $this->input('color_code', ''))),
        ]);
    }

    private function normalizedParty(): ?string
    {
        $party = strtolower(trim((string) $this->input('party', '')));

        if ($party === '' || $party === 'independent') {
            return null;
        }

        return $party;
    }
}
