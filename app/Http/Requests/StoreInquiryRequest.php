<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $this->user() !== null
            && $item !== null
            && $item->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'buyer_id' => [
                'required_without:new_buyer',
                'nullable',
                'integer',
                Rule::exists('buyers', 'id')->where('user_id', $this->user()->id),
            ],
            'new_buyer' => ['required_without:buyer_id', 'nullable', 'array'],
            'new_buyer.display_name' => ['required_with:new_buyer', 'string', 'max:255'],
            'new_buyer.phone' => ['nullable', 'string', 'max:64'],
            'new_buyer.email' => ['nullable', 'email', 'max:255'],
            'new_buyer.kijiji_handle' => ['nullable', 'string', 'max:128'],
            'message_excerpt' => ['nullable', 'string', 'max:5000'],
            'offered_price_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
