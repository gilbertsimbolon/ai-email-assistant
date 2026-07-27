<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGmailSettingsRequest extends FormRequest
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
            'client_id' => ['required', 'string', 'max:255'],
            // Left blank on purpose means "keep the currently saved secret"
            // — the real secret is never sent back to the browser to edit.
            'client_secret' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:255'],
            // An unchecked HTML checkbox sends no "enabled" key at all, so
            // this must not be 'required'.
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{client_id: string, client_secret: ?string, redirect_uri: string, enabled: bool}
     */
    public function toSettingsData(): array
    {
        return [
            'client_id' => $this->string('client_id')->trim()->toString(),
            'client_secret' => $this->filled('client_secret') ? $this->string('client_secret')->trim()->toString() : null,
            'redirect_uri' => $this->string('redirect_uri')->trim()->toString(),
            'enabled' => $this->boolean('enabled'),
        ];
    }
}
