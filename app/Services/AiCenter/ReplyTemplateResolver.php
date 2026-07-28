<?php

namespace App\Services\AiCenter;

use App\Enums\AiCenter\AiAction;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use Illuminate\Support\Collection;

class ReplyTemplateResolver
{
    /**
     * @param  Collection<int, \App\Models\AiCenter\SopAction>  $ruleActions
     */
    public function resolve(?Sop $sop, Collection $ruleActions): ?ReplyTemplate
    {
        $templateAction = $ruleActions->first(fn ($action) => $action->action_type === AiAction::ReplyUsingTemplate);

        if ($templateAction) {
            $templateId = $templateAction->payload['template_id'] ?? null;

            if ($templateId && ($template = ReplyTemplate::find($templateId))) {
                return $template;
            }
        }

        return $sop?->replyTemplate;
    }
}
