<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case OpenRouter = 'openrouter';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Ollama = 'ollama';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::OpenRouter => 'OpenRouter',
            self::Anthropic => 'Anthropic Claude',
            self::Gemini => 'Google Gemini',
            self::Ollama => 'Ollama',
        };
    }

    /**
     * Used only to pre-fill the Settings form when an admin switches
     * provider and the base_url field is still empty — never as a runtime
     * config fallback (AiConfigurationService is the single source of
     * truth once a row exists).
     */
    public function defaultBaseUrl(): string
    {
        return match ($this) {
            self::OpenAi => 'https://api.openai.com/v1',
            self::OpenRouter => 'https://openrouter.ai/api/v1',
            self::Anthropic => 'https://api.anthropic.com',
            self::Gemini => 'https://generativelanguage.googleapis.com/v1beta',
            self::Ollama => 'http://localhost:11434/v1',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::OpenAi => 'gpt-4o',
            self::OpenRouter => 'openai/gpt-4o',
            self::Anthropic => 'claude-sonnet-4-5',
            self::Gemini => 'gemini-2.0-flash',
            self::Ollama => 'llama3.1',
        };
    }
}
