<?php

namespace App\Http\Requests;

use App\Enums\ItemCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'asking_price_cents' => ['required', 'integer', 'min:1'],
            'floor_price_cents' => ['nullable', 'integer', 'min:0'],
            'location_in_house' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
