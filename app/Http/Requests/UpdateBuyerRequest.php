<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('buyer')?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'kijiji_handle' => ['nullable', 'string', 'max:128'],
            'trust_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
