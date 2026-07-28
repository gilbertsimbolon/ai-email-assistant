<?php

namespace App\Http\Controllers\AiCenter;

use App\DataTransferObjects\AiSettingsData;
use App\Enums\AiCenter\PublishStatus;
use App\Enums\AiCenter\ResponseFormat;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\AiModel;
use App\Services\AI\AiClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCenterAiModelController extends Controller
{
    public function __construct(
        protected AiClientService $aiClient,
    ) {
    }

    public function index(): View
    {
        return view('ai-center.ai-models.index', [
            'aiModels' => AiModel::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ai-center.ai-models.form', [
            'aiModel' => new AiModel(),
            'providers' => AiProvider::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $aiModel = AiModel::create($this->validated($request));

        if ($request->boolean('is_default')) {
            $aiModel->markAsDefault();
        }

        return redirect()->route('ai-center.ai-models.index')->with('success', 'AI Model berhasil dibuat.');
    }

    public function edit(AiModel $aiModel): View
    {
        return view('ai-center.ai-models.form', [
            'aiModel' => $aiModel,
            'providers' => AiProvider::cases(),
        ]);
    }

    public function update(Request $request, AiModel $aiModel): RedirectResponse
    {
        $data = $this->validated($request, $aiModel);

        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $aiModel->update($data);

        if ($request->boolean('is_default')) {
            $aiModel->markAsDefault();
        }

        return redirect()->route('ai-center.ai-models.index')->with('success', 'AI Model berhasil diperbarui.');
    }

    public function destroy(AiModel $aiModel): RedirectResponse
    {
        $aiModel->delete();

        return back()->with('success', 'AI Model berhasil dihapus.');
    }

    public function setDefault(AiModel $aiModel): RedirectResponse
    {
        $aiModel->markAsDefault();

        return back()->with('success', "\"{$aiModel->name}\" dijadikan model default.");
    }

    public function testConnection(Request $request, AiModel $aiModel): JsonResponse
    {
        $provider = AiProvider::from($request->input('provider', $aiModel->provider->value));

        $apiKey = filled($request->input('api_key')) ? $request->input('api_key') : $aiModel->api_key;

        if (blank($apiKey)) {
            return response()->json(['success' => false, 'message' => 'API Key belum diisi.'], 422);
        }

        $settings = new AiSettingsData(
            provider: $provider,
            apiKey: $apiKey,
            baseUrl: $request->input('base_url') ?: ($aiModel->base_url ?: $provider->defaultBaseUrl()),
            model: $request->input('model') ?: ($aiModel->model ?: $provider->defaultModel()),
            temperature: (float) ($request->input('temperature', $aiModel->temperature)),
            maxTokens: (int) ($request->input('max_tokens', $aiModel->max_tokens)),
            timeout: (int) ($request->input('timeout', $aiModel->timeout)),
            enabled: true,
        );

        $result = $this->aiClient->testConnection($settings);

        return response()->json($result->toArray(), $result->isSuccess() ? 200 : 422);
    }

    protected function validated(Request $request, ?AiModel $aiModel = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reasoning_effort' => ['nullable', 'string', 'max:50'],
            'presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'response_format' => ['required', Rule::enum(ResponseFormat::class)],
            'timeout' => ['required', 'integer', 'min:1', 'max:600'],
            'enabled' => ['nullable', 'boolean'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ]);

        $validated['enabled'] = $request->boolean('enabled');

        return $validated;
    }
}
