<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Position $position */
        $position = $this->route('position');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')->ignore($position?->id),
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'votes_allowed' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
