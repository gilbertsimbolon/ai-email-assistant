<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\EscalationTarget;
use App\Enums\AiCenter\Tone;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\AiCenterSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Global AI Center orchestration settings (confidence review threshold,
 * default fallback tone/escalation target, company/agent name used by
 * TemplateVariableResolver) — the "Settings" page in claude.txt's AI Center
 * menu, distinct from the legacy Settings > AI Configuration page which
 * configures the AI *provider* connection.
 */
class AiCenterSettingsController extends Controller
{
    public function edit(): View
    {
        return view('ai-center.settings', [
            'setting' => AiCenterSetting::current(),
            'tones' => Tone::cases(),
            'escalationTargets' => EscalationTarget::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confidence_review_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'default_fallback_tone' => ['required', Rule::enum(Tone::class)],
            'default_escalation_target' => ['required', Rule::enum(EscalationTarget::class)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'default_agent_name' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = AiCenterSetting::current() ?? new AiCenterSetting();
        $setting->fill($validated);
        $setting->save();

        return redirect()->route('ai-center.settings.edit')->with('success', 'Settings AI Center berhasil disimpan.');
    }
}
