<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Normalized conversation identity parsed from a GHL conversation resource.
 */
final class ParsedGhlConversationData
{
    public function __construct(
        public readonly string $ghlConversationId,
        public readonly ?string $ghlLocationId,
        public readonly ?string $contactId,
        public readonly ?string $contactName,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $subject,
        // Raw GHL channel/type string (e.g. "TYPE_EMAIL", "TYPE_SMS",
        // "TYPE_WHATSAPP", "TYPE_FACEBOOK", ...) — never mapped to a
        // restrictive local enum (claude.txt section 16), just displayed
        // as-is (prettified) in the UI.
        public readonly ?string $channel = null,
        public readonly int $unreadCount = 0,
        public readonly ?Carbon $lastActivityAt = null,
    ) {
    }

    public function isUnread(): bool
    {
        return $this->unreadCount > 0;
    }

    /**
     * Human-friendly label for the raw GHL channel string, e.g.
     * "TYPE_WHATSAPP" -> "Whatsapp". Falls back to "Conversation" when GHL
     * didn't report a channel at all — never fabricated beyond that.
     */
    public function channelLabel(): string
    {
        if (blank($this->channel)) {
            return 'Conversation';
        }

        $label = preg_replace('/^TYPE_/', '', $this->channel);
        $label = str_replace('_', ' ', $label ?? $this->channel);

        return ucwords(strtolower($label));
    }

    /**
     * Slug used for the anchor row's `channel` column and AI Center SOP
     * channel matching (App\Services\AiCenter\Engines\SopMatchingEngine) —
     * normalized to "email"/"whatsapp" so SOPs scoped to those channels
     * (the only two options the SOP builder currently offers) still match
     * GHL conversations of that type. Any other GHL channel falls back to
     * its own lowercase slug (e.g. "sms", "facebook") rather than a made-up
     * value — it just won't match an email/whatsapp-scoped SOP.
     */
    public function channelSlug(): string
    {
        $normalized = strtoupper((string) $this->channel);

        return match (true) {
            str_contains($normalized, 'EMAIL') => 'email',
            str_contains($normalized, 'WHATSAPP') => 'whatsapp',
            default => strtolower(str_replace(' ', '_', $this->channelLabel())),
        };
    }
}
