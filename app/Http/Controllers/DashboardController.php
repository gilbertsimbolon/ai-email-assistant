<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $ownedByCurrentUser = fn ($query) => $query->whereHas(
            'gmailAccount',
            fn ($q) => $q->where('user_id', $request->user()->id)
        );

        $counts = [
            'pending_review' => Conversation::where('status', ConversationStatus::PendingReview)->tap($ownedByCurrentUser)->count(),
            'replied' => Conversation::where('status', ConversationStatus::Replied)->tap($ownedByCurrentUser)->count(),
            'closed' => Conversation::where('status', ConversationStatus::Closed)->tap($ownedByCurrentUser)->count(),
        ];

        $recentConversations = Conversation::with(['analysis'])
            ->tap($ownedByCurrentUser)
            ->latest('last_message_at')
            ->limit(10)
            ->get();

        $hasGmailAccount = $request->user()->gmailAccounts()->exists();

        return view('dashboard', compact('counts', 'recentConversations', 'hasGmailAccount'));
    }
}
