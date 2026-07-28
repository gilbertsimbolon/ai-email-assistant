<?php

namespace App\Services\AiCenter\Engines;

use App\Enums\AiCenter\PublishStatus;
use App\Models\AiCenter\Intent;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves a free-text intent label (from the existing AI classification
 * JSON call — no extra AI call is made here) to a configured Intent row.
 */
class IntentDetectionEngine
{
    /**
     * Keyword-scores every published Intent against the thread text.
     *
     * @return Collection<int, Intent>
     */
    public function shortlist(string $thread): Collection
    {
        $haystack = Str::lower($thread);

        return Intent::query()
            ->published()
            ->with('keywords')
            ->get()
            ->filter(function (Intent $intent) use ($haystack) {
                foreach ($intent->keywords as $keyword) {
                    if ($keyword->keyword !== '' && str_contains($haystack, Str::lower($keyword->keyword))) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $aiAnalysisJson
     */
    public function resolve(string $thread, array $aiAnalysisJson): ?Intent
    {
        $label = $aiAnalysisJson['intent'] ?? null;
        $shortlist = $this->shortlist($thread);

        if ($label) {
            if ($match = $this->fuzzyMatch($label, $shortlist)) {
                return $match;
            }

            if ($match = $this->fuzzyMatch($label, Intent::query()->published()->get())) {
                return $match;
            }
        }

        $fallback = Intent::query()->published()->where('name', 'General Inquiry')->first();

        if ($fallback) {
            return $fallback;
        }

        return Intent::query()->published()->get()
            ->sortBy(fn (Intent $intent) => $intent->priority->weight())
            ->first();
    }

    /**
     * @param  Collection<int, Intent>  $candidates
     */
    protected function fuzzyMatch(string $label, Collection $candidates): ?Intent
    {
        $normalized = Str::lower(trim($label));

        if ($normalized === '') {
            return null;
        }

        $exact = $candidates->first(fn (Intent $intent) => Str::lower($intent->name) === $normalized);

        if ($exact) {
            return $exact;
        }

        return $candidates->first(
            fn (Intent $intent) => str_contains($normalized, Str::lower($intent->name))
                || str_contains(Str::lower($intent->name), $normalized)
        );
    }
}
