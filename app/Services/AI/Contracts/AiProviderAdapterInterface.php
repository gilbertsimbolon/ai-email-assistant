<?php

namespace App\Services\AI\Contracts;

use App\DataTransferObjects\AiChatResponse;
use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;

/**
 * Strategy/Adapter contract implemented once per AI provider. Adapters only
 * know how to translate a generic chat request into their provider's wire
 * format and classify failures — they never read AiSetting, config(), or
 * env() themselves, all configuration arrives via AiSettingsData.
 */
interface AiProviderAdapterInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, AiSettingsData $settings): AiChatResponse;

    public function testConnection(AiSettingsData $settings): AiConnectionTestResult;
}
