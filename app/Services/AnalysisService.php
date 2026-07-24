<?php

namespace App\Services;

class AnalysisService
{
    public function __construct(
        protected OpenAIService $openAI
    ) {
    }

    /**
     * Menganalisis seluruh percakapan menggunakan AI.
     */
    public function analyze(string $thread): array
    {
        //
    }

    /**
     * Menyimpan hasil analisis ke database.
     */
    public function save(array $analysis): void
    {
        //
    }
}