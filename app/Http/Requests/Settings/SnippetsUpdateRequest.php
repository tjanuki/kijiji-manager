<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SnippetsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pickup' => ['present', 'string', 'max:1000'],
            'payment' => ['present', 'string', 'max:1000'],
            'reply_templates' => ['array', 'max:20'],
            'reply_templates.*.label' => ['required_with:reply_templates.*', 'string', 'max:64'],
            'reply_templates.*.body' => ['required_with:reply_templates.*', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pickup' => (string) $this->input('pickup', ''),
            'payment' => (string) $this->input('payment', ''),
            'reply_templates' => $this->input('reply_templates', []),
        ]);
    }
}
