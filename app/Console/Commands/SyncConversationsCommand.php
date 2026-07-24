<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AnalysisService;
use App\Services\DraftService;

class SyncConversationsCommand extends Command
{
    protected $signature = 'ghl:sync';
    protected $description = 'Tarik semua percakapan dan pesan dari GoHighLevel';

    public function handle(
        GoHighLevelService $ghlService,
        AnalysisService $analysisService,
        DraftService $draftService
    ) {
        $this->info('Mengambil data percakapan dari GoHighLevel...');

        try {
            $response = $ghlService->getConversations();
            $conversations = $response['conversations'] ?? [];

            foreach ($conversations as $convoData) {
                $ghlConversationId = $convoData['id'] ?? null;
                if (!$ghlConversationId) continue;

                // 1. Simpan atau perbarui Conversation
                $conversation = Conversation::updateOrCreate(
                    ['ghl_conversation_id' => $ghlConversationId],
                    [
                        'ghl_location_id' => $convoData['locationId'] ?? config('ghl.location_id'),
                        'contact_id' => $convoData['contactId'] ?? null,
                        'contact_name' => $convoData['fullName'] ?? null,
                        'contact_email' => $convoData['email'] ?? null,
                        'contact_phone' => $convoData['phone'] ?? null,
                        'channel' => strtolower($convoData['type'] ?? 'email'),
                        'subject' => $convoData['subject'] ?? null,
                        'status' => 'pending_review',
                        'last_message_at' => isset($convoData['lastMessageDate']) ? date('Y-m-d H:i:s', $convoData['lastMessageDate'] / 1000) : now(),
                    ]
                );

                // 2. Ambil detail pesan (messages) dari percakapan ini
                $messagesResponse = $ghlService->getConversationMessages($ghlConversationId);
                $messages = $messagesResponse['messages']['messages'] ?? $messagesResponse['messages'] ?? [];

                foreach ($messages as $msgData) {
                    $ghlMessageId = $msgData['id'] ?? null;
                    if (!$ghlMessageId) continue;

                    Message::firstOrCreate(
                        ['ghl_message_id' => $ghlMessageId],
                        [
                            'conversation_id' => $conversation->id,
                            'sender_type' => ($msgData['direction'] ?? '') === 'inbound' ? 'customer' : 'agent',
                            'message_type' => $conversation->channel,
                            'body' => $msgData['body'] ?? $msgData['message'] ?? '',
                            'sent_at' => isset($msgData['dateAdded']) ? date('Y-m-d H:i:s', strtotime($msgData['dateAdded'])) : now(),
                        ]
                    );
                }

                // 3. Jalankan AI Analysis & Draft otomatis
                $conversation->load('messages');
                $threadString = $conversation->messages
                    ->map(fn($m) => "{$m->sender_type->value}: {$m->body}")
                    ->implode("\n");

                if (!empty($threadString)) {
                    $analysisData = $analysisService->analyze($threadString);
                    $analysis = $analysisService->save($conversation, $analysisData);

                    $draftContent = $draftService->generate($conversation, $analysis);
                    $draftService->save($conversation, $draftContent);
                }

                $this->info("Berhasil sinkronisasi percakapan ID: {$ghlConversationId}");
            }

            $this->info('Sinkronisasi GHL selesai!');
        } catch (\Throwable $e) {
            $this->error('Gagal sinkronisasi: ' . $e->getMessage());
        }
    }
}