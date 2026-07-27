<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class GhlConversationWebhookRequest extends FormRequest
{
    /**
     * Authorization is handled by the verify.ghl-webhook middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Payload shape is tolerant on purpose: it depends on whether this is fed by
     * a GHL Workflow "Webhook" action or a Marketplace app event subscription.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversationId' => ['required', 'string'],
            'messageId' => ['nullable', 'string'],
            'id' => ['nullable', 'string'],
            'locationId' => ['nullable', 'string'],
            'contactId' => ['nullable', 'string'],
            'contactName' => ['nullable', 'string'],
            'contactEmail' => ['nullable', 'email'],
            'contactPhone' => ['nullable', 'string'],
            'subject' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
            'dateAdded' => ['nullable'],
            'type' => ['nullable', 'string'],
        ];
    }

    /**
     * The event needs a message identifier under either `messageId` or `id`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (blank($this->input('messageId')) && blank($this->input('id'))) {
                $validator->errors()->add('messageId', 'Either messageId or id is required.');
            }
        });
    }
}
