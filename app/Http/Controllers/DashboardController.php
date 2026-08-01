<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // GHL conversations are a shared inbox (one Private Integration per
        // location, visible to every agent); legacy Gmail-synced ones stay
        // scoped to the account owner — same OR pattern as InboxController.
        $visibleToCurrentUser = fn ($query) => $query->where(
            fn ($q) => $q->whereNotNull('ghl_conversation_id')
                ->orWhereHas('gmailAccount', fn ($gq) => $gq->where('user_id', $request->user()->id))
        );

        $counts = [
            'pending_review' => Conversation::where('status', ConversationStatus::PendingReview)->tap($visibleToCurrentUser)->count(),
            'replied' => Conversation::where('status', ConversationStatus::Replied)->tap($visibleToCurrentUser)->count(),
            'closed' => Conversation::where('status', ConversationStatus::Closed)->tap($visibleToCurrentUser)->count(),
        ];

        $recentConversations = Conversation::with(['analysis'])
            ->tap($visibleToCurrentUser)
            ->latest('last_message_at')
            ->limit(10)
            ->get();

        $ghlConfigured = filled(config('ghl.api_key')) && filled(config('ghl.location_id'));

        return view('dashboard', compact('counts', 'recentConversations', 'ghlConfigured'));
    }
}
