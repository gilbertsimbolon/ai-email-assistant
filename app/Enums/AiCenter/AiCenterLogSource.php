<?php

namespace App\Enums\AiCenter;

enum AiCenterLogSource: string
{
    case Production = 'production';
    case Playground = 'playground';

    // Manual Inbox toolbar actions (Summarize/Translate/Detect Intent/
    // Extract Info/Sentiment) — see InboxToolsService. Distinct from
    // Production (the Generate/Regenerate draft pipeline) so AI Logs and
    // usage reporting can tell the two apart.
    case InboxTool = 'inbox_tool';
}
