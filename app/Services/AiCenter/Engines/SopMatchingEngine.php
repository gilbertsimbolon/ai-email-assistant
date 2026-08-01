<?php

namespace App\Services\AiCenter\Engines;

use App\Models\AiCenter\Intent;
use App\Models\AiCenter\Sop;
use App\Models\Conversation;
use App\Services\AiCenter\DataTransferObjects\SopMatchResult;
use Illuminate\Support\Str;

class SopMatchingEngine
{
    public function match(Conversation $conversation, ?Intent $intent, string $thread): SopMatchResult
    {
        $haystack = Str::lower($thread);

        $scored = Sop::query()
            ->published()
            ->with('triggers')
            ->get()
            ->filter(fn (Sop $sop) => $this->channelMatches($sop, $conversation))
            ->map(function (Sop $sop) use ($intent, $haystack) {
                $score = 0;

                if ($intent && $sop->intent_id === $intent->id) {
                    $score += 100;
                }

                foreach ($sop->triggers as $trigger) {
                    if ($trigger->phrase !== '' && str_contains($haystack, Str::lower($trigger->phrase))) {
                        $score += 10;
                    }
                }

                return ['sop' => $sop, 'score' => $score];
            })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sort(function (array $a, array $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                $weightDiff = $b['sop']->priority->weight() <=> $a['sop']->priority->weight();

                return $weightDiff !== 0 ? $weightDiff : $a['sop']->id <=> $b['sop']->id;
            });

        $best = $scored->first();

        return new SopMatchResult($best['sop'] ?? null, $best['score'] ?? 0);
    }

    protected function channelMatches(Sop $sop, Conversation $conversation): bool
    {
        if ($sop->channels === null || $sop->channels === []) {
            return true;
        }

        $channel = $conversation->channelValue();

        return $channel !== null && in_array($channel, $sop->channels, true);
    }
}
