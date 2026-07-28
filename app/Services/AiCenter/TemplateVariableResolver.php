<?php

namespace App\Services\AiCenter;

use App\Models\AiCenter\AiCenterSetting;
use App\Models\Analysis;
use App\Models\Conversation;

/**
 * Builds the value map for the 10 fixed template variables. Business fields
 * with no real billing/CRM integration yet (invoice_number, subscription_date,
 * refund_amount, product, ticket_number) read from Conversation::metadata
 * with a graceful blank fallback — see the conversations.metadata migration.
 */
class TemplateVariableResolver
{
    /**
     * @return array<string, string>
     */
    public function resolve(Conversation $conversation, ?Analysis $analysis): array
    {
        $settings = AiCenterSetting::current();
        $metadata = $conversation->metadata ?? [];

        return [
            'customer_name' => (string) ($conversation->contact_name ?: 'Customer'),
            'company' => (string) ($settings?->company_name ?: config('app.name')),
            'email' => (string) ($conversation->contact_email ?? ''),
            'invoice_number' => (string) ($metadata['invoice_number'] ?? ''),
            'subscription_date' => (string) ($metadata['subscription_date'] ?? ''),
            'refund_amount' => (string) ($metadata['refund_amount'] ?? ''),
            'today' => now()->toFormattedDateString(),
            'agent_name' => (string) (auth()->user()?->name ?? $settings?->default_agent_name ?? ''),
            'product' => (string) ($metadata['product'] ?? ''),
            'ticket_number' => (string) ($metadata['ticket_number'] ?? $conversation->id ?? ''),
        ];
    }
}
