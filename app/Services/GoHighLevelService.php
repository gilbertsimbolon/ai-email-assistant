<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoHighLevelService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $version;
    protected string $locationId;

    public function __construct()
    {
        $this->baseUrl = config('ghl.base_url');
        $this->apiKey = config('ghl.api_key');
        $this->version = config('ghl.version');
        $this->locationId = config('ghl.location_id');
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Version' => $this->version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->withoutVerifying();
    }

    public function getConversations(array $filters = [])
    {
        return $this->client()
            ->get($this->baseUrl . '/conversations', [
                'locationId' => config('ghl.location_id'),
            ])
            ->throw()
            ->json();
    }

    public function getConversationMessages(string $conversationId)
    {
        return $this->client()
            ->get($this->baseUrl . "/conversations/{$conversationId}/messages")
            ->throw()
            ->json();
    }

    public function sendMessage(array $payload)
    {
        return $this->client()
            ->post($this->baseUrl . '/conversations/messages', $payload)
            ->throw()
            ->json();
    }

    public function sendEmailMessage(string $conversationId, ?string $contactId, string $subject, string $html, string $text): array
    {
        return $this->sendMessage([
            'type' => 'Email',
            'conversationId' => $conversationId,
            'contactId' => $contactId,
            'subject' => $subject,
            'html' => $html,
            'message' => $text,
        ]);
    }
}
