<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AnalysisService;
use App\Services\DraftService;
use App\Enums\MessageType;
use App\Enums\SenderType;

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

            if (empty($conversations)) {
                $this->warn('Tidak ada percakapan ditemukan.');
                return;
            }

            $count = 0;
            foreach ($conversations as $convoData) {
                $ghlConversationId = $convoData['id'] ?? null;
                if (!$ghlConversationId) continue;

                // 1. Simpan atau perbarui Conversation
                $conversation = Conversation::updateOrCreate(
                    ['ghl_conversation_id' => $ghlConversationId],
                    [
                        'ghl_location_id' => $convoData['locationId'] ?? config('ghl.location_id'),
                        'contact_id' => $convoData['contactId'] ?? null,
                        'contact_name' => $convoData['fullName'] ?? $convoData['contactName'] ?? 'Unknown',
                        'contact_email' => $convoData['email'] ?? null,
                        'contact_phone' => $convoData['phone'] ?? null,
                        'channel' => strtolower(str_replace('TYPE_', '', $convoData['lastMessageType'] ?? 'email')),
                        'subject' => $convoData['subject'] ?? null,
                        'status' => 'pending_review',
                        'last_message_at' => isset($convoData['lastMessageDate']) ? date('Y-m-d H:i:s', $convoData['lastMessageDate'] / 1000) : now(),
                    ]
                );

                // 2. Simpan pesan terakhir dengan ghl_message_id yang unik
                if (!empty($convoData['lastMessageBody'])) {
                    $direction = $convoData['lastMessageDirection'] ?? 'inbound';
                    $senderType = $direction === 'inbound' ? SenderType::Customer : SenderType::Agent;

                    $channelValue = strtolower($conversation->channel instanceof \BackedEnum 
                        ? $conversation->channel->value 
                        : (string) $conversation->channel);

                    // Cari berdasarkan backing value dengan aman (mencocokkan lowercase)
                    $msgTypeEnum = collect(MessageType::cases())
                        ->first(fn($case) => strtolower($case->value) === $channelValue) ?? MessageType::Email;

                    $ghlMessageId = $convoData['lastMessageId'] ?? ('msg_' . $conversation->id . '_' . ($convoData['lastMessageDate'] ?? time()));

                    Message::firstOrCreate(
                        ['ghl_message_id' => $ghlMessageId],
                        [
                            'conversation_id' => $conversation->id,
                            'sender_type' => $senderType,
                            'message_type' => $msgTypeEnum,
                            'body' => $convoData['lastMessageBody'],
                            'sent_at' => isset($convoData['lastMessageDate']) ? date('Y-m-d H:i:s', $convoData['lastMessageDate'] / 1000) : now(),
                        ]
                    );
                }

                // 3. Muat pesan dan buat thread string dengan aman
                $conversation->load('messages');
                $threadString = $conversation->messages
                    ->map(function ($m) {
                        // Jika sender_type adalah BackedEnum, ambil nilainya via ->value
                        $sender = $m->sender_type instanceof \BackedEnum 
                            ? $m->sender_type->value 
                            : (string) $m->sender_type;

                        return "{$sender}: {$m->body}";
                    })
                    ->implode("\n");

                if (!empty($threadString)) {
                    $analysisData = $analysisService->analyze($threadString);
                    $analysis = $analysisService->save($conversation, $analysisData);

                    $draftContent = $draftService->generate($conversation, $analysis);
                    $draftService->save($conversation, $draftContent);
                }

                $count++;
            }

            $this->info("Sinkronisasi selesai! Berhasil memproses {$count} percakapan.");
        } catch (\Throwable $e) {
            $this->error('Gagal sinkronisasi: ' . $e->getMessage());
        }
    }
}