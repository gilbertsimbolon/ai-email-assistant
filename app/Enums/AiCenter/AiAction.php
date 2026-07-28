<?php

namespace App\Enums\AiCenter;

enum AiAction: string
{
    case GenerateReply = 'generate_reply';
    case ReplyUsingTemplate = 'reply_using_template';
    case ReplyUsingKnowledgeBase = 'reply_using_knowledge_base';
    case Escalate = 'escalate';
    case AssignHuman = 'assign_human';
    case AddInternalNote = 'add_internal_note';
    case TagConversation = 'tag_conversation';
    case CloseConversation = 'close_conversation';
    case Ignore = 'ignore';
    case MarkSpam = 'mark_spam';
    case NoReply = 'no_reply';

    public function label(): string
    {
        return match ($this) {
            self::GenerateReply => 'Generate Reply',
            self::ReplyUsingTemplate => 'Reply using Template',
            self::ReplyUsingKnowledgeBase => 'Reply using Knowledge Base',
            self::Escalate => 'Escalate',
            self::AssignHuman => 'Assign Human',
            self::AddInternalNote => 'Add Internal Note',
            self::TagConversation => 'Tag Conversation',
            self::CloseConversation => 'Close Conversation',
            self::Ignore => 'Ignore',
            self::MarkSpam => 'Mark Spam',
            self::NoReply => 'No Reply',
        };
    }
}
