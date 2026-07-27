<?php

use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Message;
use Illuminate\Support\Facades\Http;

function fakeOpenAiResponses(): void
{
    $analysis = [
        'language' => 'English',
        'intent' => 'Refund Request',
        'priority' => 'High',
        'sentiment' => 'Negative',
        'customer_status' => 'existing_customer',
        'needs_escalation' => true,
        'refund_requested' => true,
        'summary' => 'Customer accidentally paid twice and wants a refund.',
        'last_customer_request' => 'Please refund my duplicate payment.',
        'recommended_action' => 'Escalate to Billing Team',
        'confidence_score' => 0.92,
    ];

    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(chatCompletion(json_encode($analysis)))
            ->push(chatCompletion('Halo, mohon maaf atas ketidaknyamanannya. Kami akan segera memproses refund Anda.')),
        '*' => Http::response([], 200),
    ]);

    config(['openai.api_key' => 'test-key']);
}

function chatCompletion(string $content): array
{
    return [
        'id' => 'chatcmpl-test',
        'model' => 'gpt-4o',
        'choices' => [
            ['message' => ['content' => $content]],
        ],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
    ];
}

function webhookPayload(array $overrides = []): array
{
    return array_merge([
        'conversationId' => 'ghl-conv-1',
        'messageId' => 'ghl-msg-1',
        'locationId' => 'loc-1',
        'contactId' => 'contact-1',
        'contactName' => 'Jane Doe',
        'contactEmail' => 'jane@example.com',
        'body' => 'I was charged twice for my last order, please help.',
        'direction' => 'inbound',
        'dateAdded' => now()->toIso8601String(),
    ], $overrides);
}

beforeEach(function () {
    config(['webhook.ghl_secret' => 'test-secret']);
});

test('rejects a GHL webhook without a valid shared secret', function () {
    $response = $this->postJson(route('webhooks.ghl.conversation'), webhookPayload());

    $response->assertStatus(401);
    expect(Conversation::count())->toBe(0);
});

test('processes a valid GHL webhook into conversation, message, analysis and draft', function () {
    fakeOpenAiResponses();

    $response = $this->postJson(
        route('webhooks.ghl.conversation'),
        webhookPayload(),
        ['X-GHL-Webhook-Secret' => 'test-secret']
    );

    $response->assertStatus(202);

    $conversation = Conversation::where('ghl_conversation_id', 'ghl-conv-1')->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->contact_email)->toBe('jane@example.com');

    $message = Message::where('ghl_message_id', 'ghl-msg-1')->first();
    expect($message)->not->toBeNull();
    expect($message->sender_type)->toBe(SenderType::Customer);

    // Regression coverage for the AnalysisService field-mapping bug: these
    // columns used to be silently dropped by mass-assignment.
    expect($conversation->analysis)->not->toBeNull();
    expect($conversation->analysis->customer_intent)->toBe('Refund Request');
    expect($conversation->analysis->escalation_required)->toBeTrue();
    expect($conversation->analysis->customer_status->value)->toBe('existing_customer');

    // Regression coverage for the DraftService arity bug: a Draft row must
    // actually be persisted, not just generated and discarded.
    $draft = Draft::where('conversation_id', $conversation->id)->first();
    expect($draft)->not->toBeNull();
    expect($draft->content)->not->toBeEmpty();
});

test('does not duplicate a message already processed for the same ghl_message_id', function () {
    fakeOpenAiResponses();

    $this->postJson(
        route('webhooks.ghl.conversation'),
        webhookPayload(),
        ['X-GHL-Webhook-Secret' => 'test-secret']
    )->assertStatus(202);

    $this->postJson(
        route('webhooks.ghl.conversation'),
        webhookPayload(['body' => 'duplicate delivery of the same event']),
        ['X-GHL-Webhook-Secret' => 'test-secret']
    )->assertStatus(202);

    expect(Message::where('ghl_message_id', 'ghl-msg-1')->count())->toBe(1);
    expect(Conversation::count())->toBe(1);
});
