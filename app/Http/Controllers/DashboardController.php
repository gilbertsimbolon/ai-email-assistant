<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Conversation;

class DashboardController extends Controller
{
    public function index()
    {
        $counts = [
            'pending_review' => Conversation::where('status', ConversationStatus::PendingReview)->count(),
            'replied' => Conversation::where('status', ConversationStatus::Replied)->count(),
            'closed' => Conversation::where('status', ConversationStatus::Closed)->count(),
        ];

        $recentConversations = Conversation::with(['analysis'])
            ->latest('last_message_at')
            ->limit(10)
            ->get();

        return view('dashboard', compact('counts', 'recentConversations'));
    }
}
