<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\AiSettingsData;
use App\Enums\AiProvider;
use App\Http\Requests\UpdateAiSettingsRequest;
use App\Services\AI\AiClientService;
use App\Services\AI\AiConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Lets an administrator manage the global AI provider configuration
 * (provider, api key, base url, model, temperature, max tokens, timeout)
 * from the Settings page instead of the .env file. Restricted to admins by
 * the 'admin' route middleware.
 */
class AiSettingsController extends Controller
{
    public function __construct(
        protected AiConfigurationService $aiConfig,
        protected AiClientService $aiClient,
    ) {}

    public function index(): View
    {
        return view('settings.ai-config', [
            'providers' => AiProvider::cases(),
            'provider' => $this->aiConfig->getProvider(),
            'baseUrl' => $this->aiConfig->getBaseUrl(),
            'model' => $this->aiConfig->getModel(),
            'temperature' => $this->aiConfig->getTemperature(),
            'maxTokens' => $this->aiConfig->getMaxTokens(),
            'timeout' => $this->aiConfig->getTimeout(),
            'enabled' => $this->aiConfig->isEnabled(),
            'hasApiKey' => $this->aiConfig->isConfigured(),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $this->aiConfig->save($request->toSettingsData());

        return redirect()->route('settings.ai-config.index')
            ->with('success', 'Konfigurasi AI berhasil disimpan.');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', new Enum(AiProvider::class)],
            'api_key' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:1000000'],
            'timeout' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        $provider = AiProvider::from($validated['provider']);

        // An admin testing an unchanged key won't have retyped it, so fall
        // back to the currently saved one — the same "blank means keep it"
        // rule the save form uses.
        $apiKey = filled($validated['api_key'] ?? null)
            ? $validated['api_key']
            : $this->aiConfig->getApiKey();

        if (blank($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key belum diisi.',
            ], 422);
        }

        $settings = new AiSettingsData(
            provider: $provider,
            apiKey: $apiKey,
            baseUrl: filled($validated['base_url'] ?? null) ? $validated['base_url'] : $provider->defaultBaseUrl(),
            model: $validated['model'],
            temperature: (float) $validated['temperature'],
            maxTokens: (int) $validated['max_tokens'],
            timeout: (int) $validated['timeout'],
            enabled: true,
        );

        Log::info('Test AI Connection', [
            'provider' => $provider->value,
            'model' => $settings->model,
        ]);

        $result = $this->aiClient->testConnection($settings);

        return response()->json($result->toArray(), $result->isSuccess() ? 200 : 422);
    }
}
