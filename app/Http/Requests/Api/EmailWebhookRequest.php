<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class EmailWebhookRequest extends FormRequest
{
    /**
     * Authorization is handled by the verify.webhook middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ];
    }
}
