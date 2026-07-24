<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AnalysisService;
use App\Services\DraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller // Menggunakan Controller bawaan Laravel
{
    public function __construct(
        protected AnalysisService $analysisService,
        protected DraftService $draftService
    ) {}

    public function handle(Request $request)
    {
        Log::info('Webhook received', $request->all());

        $data = $request->json()->all();

        $ghlConversationId = $data['conversationId'] ?? null;
        $ghlLocationId = $data['locationId'] ?? null;
        $ghlMessageId = $data['messageId'] ?? null;

        if (!$ghlConversationId || !$ghlMessageId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        try {
            DB::beginTransaction();

            $conversation = Conversation::firstOrCreate(
                ['ghl_conversation_id' => $ghlConversationId],
                [
                    'ghl_location_id' => $ghlLocationId,
                    'contact_id' => $data['contactId'] ?? null,
                    'contact_name' => $data['contactName'] ?? null,
                    'contact_email' => $data['contactEmail'] ?? null,
                    'contact_phone' => $data['contactPhone'] ?? null,
                    'channel' => strtolower($data['channel'] ?? 'whatsapp'),
                    'subject' => $data['subject'] ?? null,
                    'status' => 'pending_review',
                    'last_message_at' => now(),
                ]
            );

            // Update timestamp terakhir jika percakapan sudah ada sebelumnya
            $conversation->update(['last_message_at' => now()]);

            Message::create([
                'conversation_id' => $conversation->id,
                'ghl_message_id' => $ghlMessageId,
                'sender_type' => $data['senderType'] ?? 'customer',
                'message_type' => $conversation->channel,
                'body' => $data['body'] ?? '',
                'sent_at' => now(),
            ]);

            $conversation->load('messages');

            $threadString = $conversation->messages
                ->map(function ($m) {
                    // Ambil nilai string dari enum sender_type
                    $sender = $m->sender_type instanceof \BackedEnum ? $m->sender_type->value : (string) $m->sender_type;
                    
                    return "{$sender}: {$m->body}";
                })
                ->implode("\n");

            $analysisData = $this->analysisService->analyze($threadString);
            $analysis = $this->analysisService->save($conversation, $analysisData);

            $draftContent = $this->draftService->generate($conversation, $analysis);
            $this->draftService->save($conversation, $draftContent);

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Processed successfully']);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}