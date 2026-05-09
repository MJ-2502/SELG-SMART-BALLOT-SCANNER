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

    protected function prepareForValidation(): void
    {
        $party = trim((string) $this->input('party', ''));
        
        // 1. Sanitize ALL candidate names in the array to Title Case
        $entries = $this->input('entries', []);
        if (is_array($entries)) {
            foreach ($entries as $positionId => $value) {
                // Support either a single name (string) or multiple names (array)
                if (is_array($value)) {
                    foreach ($value as $idx => $name) {
                        $trimmed = trim((string) $name);
                        $entries[$positionId][$idx] = $trimmed !== '' ? ucwords($trimmed) : '';
                    }
                } else {
                    $trimmed = trim((string) $value);
                    $entries[$positionId] = $trimmed !== '' ? ucwords($trimmed) : '';
                }
            }
        }

        $this->merge([
            'party' => $party !== '' ? strtoupper($party) : null,
            'color_code' => strtoupper(trim((string) $this->input('color_code', ''))),
            'entries' => $entries, // Save the sanitized names back
        ]);
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
            
            // --- THE CORRECTED RULES ---
            'entries' => ['required', 'array'],
            'entries.*' => ['required', 'array'], 
            'entries.*.*' => ['nullable', 'string', 'max:255'], 
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $entriesData = $this->input('entries', []);
            $flatNames = [];
            
            // Extract all names that were typed in
            foreach ($entriesData as $positionId => $namesArray) {
                foreach ((array) $namesArray as $name) {
                    $trimmed = trim((string) $name);
                    if ($trimmed !== '') {
                        $flatNames[] = ['position_id' => $positionId, 'name' => $trimmed];
                    }
                }
            }

            if (empty($flatNames)) {
                $validator->errors()->add('entries', 'Add at least one candidate name for this partylist.');
                return;
            }

            // Check if they typed the exact same name multiple times in the form
            $justNames = array_column($flatNames, 'name');
            $nameCounts = array_count_values($justNames);

            // Run our strict double-checker on the database
            foreach ($flatNames as $entry) {
                if ($nameCounts[$entry['name']] > 1) {
                    $validator->errors()->add("entries", "You entered '{$entry['name']}' multiple times in this form.");
                    continue;
                }

                $existingCandidate = Candidate::with('position')->where('name', $entry['name'])->first();

                if ($existingCandidate) {
                    if ($existingCandidate->position_id == $entry['position_id']) {
                        $validator->errors()->add("entries", "{$entry['name']} is already running for this position.");
                    } else {
                        $positionName = $existingCandidate->position ? $existingCandidate->position->name : 'another position';
                        $validator->errors()->add("entries", "{$entry['name']} is already running for {$positionName}. A candidate cannot run for multiple positions.");
                    }
                }
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