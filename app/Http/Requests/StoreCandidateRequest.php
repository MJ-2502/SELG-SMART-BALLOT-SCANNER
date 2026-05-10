<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
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

        return [
            'position_id' => ['required', 'exists:positions,id'],
            
            // Replaced the simple unique rule with a custom double-checker
            'name' => [
                'required', 
                'string', 
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    // Look for any existing candidate with this exact name
                    $existingCandidate = Candidate::with('position')->where('name', $value)->first();
                    
                    if ($existingCandidate) {
                        // Checker 1: Are they already in THIS position?
                        if ($existingCandidate->position_id == $this->position_id) {
                            $fail('This exact candidate is already running for this position.');
                        } 
                        // Checker 2: Are they running for ANOTHER position?
                        else {
                            $positionName = $existingCandidate->position ? $existingCandidate->position->name : 'another position';
                            $fail("This candidate is already running for {$positionName}. A candidate cannot run for multiple positions.");
                        }
                    }
                }
            ],
            
            'party' => ['nullable', 'string', 'max:255'],
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $party = $this->normalizedParty();
            $positionId = $this->input('position_id');

            // Capacity Checker: Only check if they selected a party and a position
            if ($party !== null && $positionId) {
                $position = \App\Models\Position::find($positionId);
                
                if ($position) {
                    // Count how many candidates are currently in this exact party + position
                    $currentCount = \App\Models\Candidate::query()
                        ->where('position_id', $positionId)
                        ->whereRaw('LOWER(TRIM(party)) = ?', [$party])
                        ->count();

                    $maxAllowed = $position->max_candidates_per_party ?? 1;

                    // Block submission if the roster is full
                    if ($currentCount >= $maxAllowed) {
                        $partyName = $this->input('party');
                        $validator->errors()->add(
                            'party', 
                            "The partylist \"{$partyName}\" already has the maximum allowed candidates ({$maxAllowed}) for {$position->name}."
                        );
                    }
                }
            }
        });
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