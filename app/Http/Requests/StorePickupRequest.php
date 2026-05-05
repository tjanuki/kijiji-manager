<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'buyer_id' => [
                'required',
                'integer',
                Rule::exists('buyers', 'id')->where('user_id', $userId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('user_id', $userId),
            ],
            'items.*.agreed_price_cents' => ['required', 'integer', 'min:0'],
        ];
    }
}
