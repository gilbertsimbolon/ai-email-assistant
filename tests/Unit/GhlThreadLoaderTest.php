<?php

use App\DataTransferObjects\ParsedGhlMessageData;
use App\Services\Ghl\GhlParserService;
use App\Services\Ghl\GhlThreadLoader;
use App\Services\Ghl\GoHighLevelApiService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

uses(TestCase::class);

it('loads every page of a conversation thread and preserves the full ordered list', function () {
    $api = Mockery::mock(GoHighLevelApiService::class);
    $parser = Mockery::mock(GhlParserService::class);

    $api->shouldReceive('getConversationMessages')
        ->once()
        ->with('conv-1', ['limit' => 100])
        ->andReturn([
            'messages' => [
                ['id' => 'm-1', 'body' => 'first', 'dateAdded' => '2024-01-01T00:00:00Z'],
                ['id' => 'm-2', 'body' => 'second', 'dateAdded' => '2024-01-03T00:00:00Z'],
            ],
            'nextPageToken' => 'token-2',
        ]);

    $api->shouldReceive('getConversationMessages')
        ->once()
        ->with('conv-1', ['limit' => 100, 'nextPageToken' => 'token-2'])
        ->andReturn([
            'messages' => [
                ['id' => 'm-2', 'body' => 'second', 'dateAdded' => '2024-01-03T00:00:00Z'],
                ['id' => 'm-3', 'body' => 'third', 'dateAdded' => '2024-01-05T00:00:00Z'],
            ],
        ]);

    $parser->shouldReceive('messageFromSearchApi')
        ->andReturnUsing(function (array $raw) {
            return new ParsedGhlMessageData(
                ghlMessageId: (string) ($raw['id'] ?? ''),
                direction: 'outbound',
                body: (string) ($raw['body'] ?? ''),
                sentAt: Carbon::parse($raw['dateAdded'] ?? now()),
            );
        });

    $loader = new GhlThreadLoader($api, $parser);
    $messages = $loader->messages('conv-1');

    expect($messages->pluck('ghl_message_id')->all())->toBe(['m-1', 'm-2', 'm-3']);
    expect($messages->pluck('body')->all())->toBe(['first', 'second', 'third']);
});
