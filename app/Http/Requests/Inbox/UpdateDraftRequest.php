<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftRequest extends FormRequest
{
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
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
