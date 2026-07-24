<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case PendingReview = 'pending_review';
    case Replied = 'replied';
    case Closed = 'closed';
}