<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $party = trim((string) $this->input('party', ''));
        $colorCode = strtoupper(trim((string) $this->input('color_code', '')));
        $name = ucwords(trim((string) $this->input('name', '')));

        $this->merge([
            'name' => $name !== '' ? $name : null,
            
            // Standardize partylist casing to UPPERCASE to prevent duplicate groups
            'party' => $party !== '' ? strtoupper($party) : null, 
            
            'color_code' => $colorCode,
        ]);
    }

    public function rules(): array
    {
        $normalizedParty = $this->normalizedParty();
        $candidate = $this->route('candidate');
        $ignoreCandidateId = $candidate instanceof Candidate ? $candidate->id : (is_numeric($candidate) ? (int) $candidate : null);

        return [
            'position_id' => ['required', 'exists:positions,id'],
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('candidates')->where(function ($query) {
                    return $query->where('position_id', $this->position_id);
                })->ignore($ignoreCandidateId) // Bypasses the strict check for the current record
            ],
            'party' => ['nullable', 'string', 'max:255'],
            'color_code' => [
                'required',
                'regex:/^#[0-9A-F]{6}$/',
                Rule::in(config('candidate_colors.palette', [])),
                function (string $attribute, mixed $value, \Closure $fail) use ($normalizedParty, $ignoreCandidateId): void {
                    $message = $this->validateColorOwnership((string) $value, $normalizedParty, $ignoreCandidateId);

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This exact candidate is already running for this position.',
        ];
    }

    private function normalizedParty(): ?string
    {
        $party = trim((string) $this->input('party', ''));

        if ($party === '') {
            return null;
        }

        return strtolower($party);
    }

    private function validateColorOwnership(string $colorCode, ?string $normalizedParty, ?int $ignoreCandidateId = null): ?string
    {
        if ($normalizedParty !== null) {
            $partyColorQuery = Candidate::query()
                ->whereNotNull('party')
                ->whereRaw('LOWER(TRIM(party)) = ?', [$normalizedParty])
                ->whereNotNull('color_code');

            if ($ignoreCandidateId !== null) {
                $partyColorQuery->whereKeyNot($ignoreCandidateId);
            }

            $partyColors = $partyColorQuery
                ->selectRaw('UPPER(color_code) as color_code')
                ->distinct()
                ->pluck('color_code');

            if ($partyColors->isNotEmpty() && !$partyColors->contains($colorCode)) {
                $partyLabel = trim((string) $this->input('party', 'this partylist'));

                return "Partylist \"{$partyLabel}\" already uses {$partyColors->first()}.";
            }
        }

        $conflictQuery = Candidate::query()
            ->whereRaw('UPPER(color_code) = ?', [$colorCode]);

        if ($ignoreCandidateId !== null) {
            $conflictQuery->whereKeyNot($ignoreCandidateId);
        }

        if ($normalizedParty !== null) {
            $conflictQuery->where(function ($query) use ($normalizedParty): void {
                $query
                    ->whereNull('party')
                    ->orWhereRaw('LOWER(TRIM(party)) <> ?', [$normalizedParty]);
            });
        }

        if ($conflictQuery->exists()) {
            return 'This color is already assigned to another partylist or independent candidate.';
        }

        return null;
    }
}