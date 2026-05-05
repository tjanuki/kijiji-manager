<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pickup = $this->route('pickup');

        return $this->user() !== null
            && $pickup !== null
            && $pickup->buyer->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
