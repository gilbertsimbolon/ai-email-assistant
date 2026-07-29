<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\ResponseFormat;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\AiModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Quick-access tuning for the default AI Model's parameters (Temperature,
 * Top P, Max Tokens, Reasoning Effort, Presence Penalty, Frequency Penalty,
 * Response Format) per claude.txt's "AI Parameters" page — a shortcut over
 * editing the full AI Model form for the model that's actually in use.
 */
class AiCenterAiParameterController extends Controller
{
    public function edit(): View
    {
        return view('ai-center.ai-parameters.edit', [
            'aiModel' => AiModel::default(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $aiModel = AiModel::default();

        if (! $aiModel) {
            return redirect()->route('ai-center.ai-parameters.edit')
                ->with('error', 'Belum ada AI Model default. Tambahkan AI Model terlebih dahulu di menu AI Models.');
        }

        $validated = $request->validate([
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reasoning_effort' => ['nullable', 'string', 'max:50'],
            'presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'response_format' => ['required', Rule::enum(ResponseFormat::class)],
        ]);

        $aiModel->update($validated);

        return redirect()->route('ai-center.ai-parameters.edit')
            ->with('success', 'AI Parameters berhasil diperbarui.');
    }
}
