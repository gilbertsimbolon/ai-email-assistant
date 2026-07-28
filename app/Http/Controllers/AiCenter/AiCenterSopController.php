<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\PriorityLevel;
use App\Enums\AiCenter\PublishStatus;
use App\Enums\AiCenter\SupportedChannel;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\Category;
use App\Models\AiCenter\ForbiddenAction;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\KnowledgeBase;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCenterSopController extends Controller
{
    public function index(): View
    {
        return view('ai-center.sops.index', [
            'sops' => Sop::query()->with('category', 'intent')->withCount('rules')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ai-center.sops.form', array_merge(
            ['sop' => new Sop(['channels' => null])],
            $this->formOptions()
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $sop = Sop::create($this->validated($request));

        $this->syncTriggers($sop, $request->input('triggers', []));
        $sop->knowledgeBases()->sync($request->input('knowledge_base_ids', []));
        $sop->forbiddenActions()->sync($request->input('forbidden_action_ids', []));

        return redirect()->route('ai-center.sops.edit', $sop)->with('success', 'SOP berhasil dibuat. Sekarang tambahkan Rule.');
    }

    public function edit(Sop $sop): View
    {
        $sop->load('triggers', 'rules.conditions', 'rules.actions', 'knowledgeBases', 'forbiddenActions');

        return view('ai-center.sops.form', array_merge(['sop' => $sop], $this->formOptions()));
    }

    public function update(Request $request, Sop $sop): RedirectResponse
    {
        $sop->update($this->validated($request, $sop));

        $this->syncTriggers($sop, $request->input('triggers', []));
        $sop->knowledgeBases()->sync($request->input('knowledge_base_ids', []));
        $sop->forbiddenActions()->sync($request->input('forbidden_action_ids', []));

        return redirect()->route('ai-center.sops.edit', $sop)->with('success', 'SOP berhasil diperbarui.');
    }

    public function destroy(Sop $sop): RedirectResponse
    {
        $sop->delete();

        return redirect()->route('ai-center.sops.index')->with('success', 'SOP berhasil dihapus.');
    }

    protected function validated(Request $request, ?Sop $sop = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'intent_id' => ['nullable', 'exists:intents,id'],
            'priority' => ['required', Rule::enum(PriorityLevel::class)],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'description' => ['nullable', 'string'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::enum(SupportedChannel::class)],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'reply_template_id' => ['nullable', 'exists:reply_templates,id'],
        ]);

        $validated['channels'] = $validated['channels'] ?? null;

        return $validated;
    }

    protected function syncTriggers(Sop $sop, array $phrases): void
    {
        $sop->triggers()->delete();

        foreach (array_filter(array_map('trim', $phrases)) as $phrase) {
            $sop->triggers()->create(['phrase' => $phrase]);
        }
    }

    protected function formOptions(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(),
            'intents' => Intent::query()->orderBy('name')->get(),
            'workflows' => Workflow::query()->orderBy('name')->get(),
            'replyTemplates' => ReplyTemplate::query()->orderBy('name')->get(),
            'knowledgeBases' => KnowledgeBase::query()->orderBy('title')->get(),
            'forbiddenActions' => ForbiddenAction::query()->orderBy('label')->get(),
            'channelOptions' => SupportedChannel::cases(),
        ];
    }
}
