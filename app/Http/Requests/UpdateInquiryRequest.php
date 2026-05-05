<?php

namespace App\Http\Requests;

use App\Enums\InquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inquiry = $this->route('inquiry');

        return $this->user() !== null
            && $inquiry !== null
            && $inquiry->item->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(InquiryStatus::class)],
            'offered_price_cents' => ['nullable', 'integer', 'min:0'],
            'negotiation_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
