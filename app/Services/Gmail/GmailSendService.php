<?php

namespace App\Services\Gmail;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Enums\SenderType;
use App\Models\Draft;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends an approved AI draft back out through the Gmail API, as a reply in
 * the same Gmail thread. Replaces the old GHL-backed EmailService.
 */
class GmailSendService
{
    public function __construct(
        protected GmailApiService $api,
        protected GmailAuthService $auth,
    ) {
    }

    public function sendDraft(Draft $draft): void
    {
        $conversation = $draft->conversation;
        $account = $conversation?->gmailAccount;

        if (!$account) {
            throw new RuntimeException('Percakapan ini belum terhubung dengan akun Gmail manapun.');
        }

        if (blank($conversation->contact_email)) {
            throw new RuntimeException('Alamat email tujuan tidak diketahui, tidak bisa mengirim balasan.');
        }

        $account = $this->auth->ensureFreshToken($account);

        $subject = $draft->content['subject'] ?? ('Re: '.($conversation->subject ?? 'percakapan Anda'));
        $body = $draft->content['body'] ?? '';

        $lastInbound = $conversation->messages()
            ->where('sender_type', SenderType::Customer)
            ->latest('sent_at')
            ->first();

        $raw = $this->buildRawMessage(
            $account->email,
            $conversation->contact_email,
            $subject,
            $body,
            $lastInbound?->message_id_header,
        );

        $this->api->sendRawMessage($account->access_token, $raw, $conversation->gmail_thread_id);

        $draft->update(['status' => DraftStatus::Sent]);
        $conversation->update(['status' => ConversationStatus::Replied]);

        Log::info('Draft sent via Gmail', [
            'conversation_id' => $conversation->id,
            'draft_id' => $draft->id,
        ]);
    }

    /**
     * Build an RFC 2822 message and base64url-encode it, as required by the
     * Gmail API's `raw` message field.
     */
    protected function buildRawMessage(string $from, string $to, string $subject, string $body, ?string $inReplyTo): string
    {
        $headers = [
            'From' => $from,
            'To' => $to,
            'Subject' => $this->encodeHeader($subject),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];

        if ($inReplyTo) {
            // Threading: without these headers Gmail may still group the
            // message by subject/threadId, but proper RFC clients rely on this.
            $headers['In-Reply-To'] = $inReplyTo;
            $headers['References'] = $inReplyTo;
        }

        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        $lines[] = '';
        $lines[] = $body;

        $message = implode("\r\n", $lines);

        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }

    protected function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?'.base64_encode($value).'?=';
        }

        return $value;
    }
}
