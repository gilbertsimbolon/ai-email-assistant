<?php

namespace App\Http\Requests;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level 'admin' middleware already restricts access; this
        // stays true the same way the rest of the app's FormRequests do.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', new Enum(AiProvider::class)],
            // Left blank on purpose means "keep the currently saved key" —
            // the real key is never sent back to the browser to edit.
            'api_key' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:1000000'],
            'timeout' => ['required', 'integer', 'min:1', 'max:600'],
            // An unchecked HTML checkbox sends no "enabled" key at all, so
            // this must not be 'required'.
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{provider: AiProvider, api_key: ?string, base_url: ?string, model: string, temperature: float, max_tokens: int, timeout: int, enabled: bool}
     */
    public function toSettingsData(): array
    {
        return [
            'provider' => AiProvider::from($this->string('provider')->toString()),
            'api_key' => $this->filled('api_key') ? $this->string('api_key')->trim()->toString() : null,
            'base_url' => $this->filled('base_url') ? $this->string('base_url')->trim()->toString() : null,
            'model' => $this->string('model')->trim()->toString(),
            'temperature' => (float) $this->input('temperature'),
            'max_tokens' => (int) $this->input('max_tokens'),
            'timeout' => (int) $this->input('timeout'),
            'enabled' => $this->boolean('enabled'),
        ];
    }
}
