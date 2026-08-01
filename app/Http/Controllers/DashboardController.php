<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // GHL conversations are never mirrored locally anymore (claude.txt),
        // so there's no cheap accurate count/recent-list for them without an
        // extra GHL API call per tile — this dashboard widget only covers
        // the still-DB-backed Gmail Inbox. GHL's own numbers live on the
        // Conversations page itself, which always reads GHL live.
        $visibleToCurrentUser = fn ($query) => $query->whereHas(
            'gmailAccount',
            fn ($gq) => $gq->where('user_id', $request->user()->id)
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

        return view('dashboard', compact('counts', 'recentConversations'));
    }
}
