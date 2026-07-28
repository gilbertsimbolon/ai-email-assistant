<?php

namespace App\Services\AiCenter;

use App\Enums\AiCenter\PublishStatus;
use App\Models\AiCenter\KnowledgeBase;
use App\Models\AiCenter\Sop;
use Illuminate\Support\Collection;

/**
 * Returns the published Knowledge Base documents an admin curated onto a
 * SOP. No runtime relevance ranking in this phase — the admin's own
 * curation via the sop_knowledge_base pivot already scopes the set to a
 * handful of relevant documents.
 */
class KnowledgeResolver
{
    /**
     * @return Collection<int, KnowledgeBase>
     */
    public function resolve(?Sop $sop): Collection
    {
        if (! $sop) {
            return collect();
        }

        return $sop->knowledgeBases()
            ->where('status', PublishStatus::Published)
            ->orderBy('sort_order')
            ->get();
    }
}
