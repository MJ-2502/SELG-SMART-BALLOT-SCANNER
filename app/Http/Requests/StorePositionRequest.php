<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:positions,name'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'votes_allowed' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
