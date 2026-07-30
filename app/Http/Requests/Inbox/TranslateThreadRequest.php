<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TranslateThreadRequest extends FormRequest
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
            'language' => ['required', Rule::in(['en', 'id', 'ja', 'zh', 'es', 'fr', 'de'])],
            'force_refresh' => ['sometimes', 'boolean'],
        ];
    }
}
