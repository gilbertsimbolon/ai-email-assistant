<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoHighLevelService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $version;

    public function __construct()
    {
        $this->baseUrl = config('ghl.base_url');
        $this->apiKey = config('ghl.api_key');
        $this->version = config('ghl.version');
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

    public function getConversations()
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
}
