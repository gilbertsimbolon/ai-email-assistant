<?php

namespace App\Services\Reports;

/**
 * Deliberately simple, config-driven ($config/ai_pricing.php) cost formula:
 * price-per-1K-tokens by model name, with a default fallback. Swap in real
 * vendor pricing later by editing the config only.
 */
class AiCostEstimator
{
    public function estimate(?string $modelName, ?int $promptTokens, ?int $completionTokens): float
    {
        $rates = $this->ratesFor($modelName);

        $promptCost = ($promptTokens ?? 0) / 1000 * $rates['prompt'];
        $completionCost = ($completionTokens ?? 0) / 1000 * $rates['completion'];

        return round($promptCost + $completionCost, 4);
    }

    /**
     * @return array{prompt: float, completion: float}
     */
    protected function ratesFor(?string $modelName): array
    {
        $models = config('ai_pricing.models', []);
        $needle = strtolower((string) $modelName);

        foreach ($models as $key => $rates) {
            if ($needle !== '' && str_contains($needle, strtolower($key))) {
                return $rates;
            }
        }

        return config('ai_pricing.default', ['prompt' => 0.0, 'completion' => 0.0]);
    }
}
