<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\AiAction;
use App\Enums\AiCenter\BooleanOperator;
use App\Enums\AiCenter\ConditionField;
use App\Enums\AiCenter\ConditionOperator;
use App\Enums\AiCenter\EscalationTarget;
use App\Enums\AiCenter\Tone;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\SopRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCenterSopRuleController extends Controller
{
    public function create(Sop $sop): View
    {
        return view('ai-center.sops.rules.form', array_merge(['sop' => $sop, 'rule' => new SopRule()], $this->formOptions()));
    }

    public function store(Request $request, Sop $sop): RedirectResponse
    {
        $rule = $sop->rules()->create($this->validatedRule($request, $sop));

        $this->syncConditions($rule, $request->input('conditions', []));
        $this->syncActions($rule, $request->input('actions', []));

        return redirect()->route('ai-center.sops.edit', $sop)->with('success', 'Rule berhasil dibuat.');
    }

    public function edit(SopRule $rule): View
    {
        $rule->load('conditions', 'actions');

        return view('ai-center.sops.rules.form', array_merge(['sop' => $rule->sop, 'rule' => $rule], $this->formOptions()));
    }

    public function update(Request $request, SopRule $rule): RedirectResponse
    {
        $rule->update($this->validatedRule($request, $rule->sop));

        $this->syncConditions($rule, $request->input('conditions', []));
        $this->syncActions($rule, $request->input('actions', []));

        return redirect()->route('ai-center.sops.edit', $rule->sop)->with('success', 'Rule berhasil diperbarui.');
    }

    public function destroy(SopRule $rule): RedirectResponse
    {
        $sop = $rule->sop;
        $rule->delete();

        return redirect()->route('ai-center.sops.edit', $sop)->with('success', 'Rule berhasil dihapus.');
    }

    protected function validatedRule(Request $request, Sop $sop): array
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'tone' => ['nullable', Rule::enum(Tone::class)],
            'escalation_target' => ['nullable', Rule::enum(EscalationTarget::class)],
        ]);

        $validated['sop_id'] = $sop->id;
        $validated['order'] ??= $sop->rules()->count();

        return $validated;
    }

    protected function syncConditions(SopRule $rule, array $conditions): void
    {
        $rule->conditions()->delete();

        foreach (array_values($conditions) as $index => $condition) {
            if (blank($condition['field'] ?? null)) {
                continue;
            }

            $rule->conditions()->create([
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => $condition['value'] ?? null,
                'boolean_operator' => $condition['boolean_operator'] ?? BooleanOperator::And->value,
                'order' => $index,
            ]);
        }
    }

    protected function syncActions(SopRule $rule, array $actions): void
    {
        $rule->actions()->delete();

        foreach (array_values($actions) as $index => $action) {
            if (blank($action['action_type'] ?? null)) {
                continue;
            }

            $payload = [];

            if (! empty($action['template_id'])) {
                $payload['template_id'] = (int) $action['template_id'];
            }

            $rule->actions()->create([
                'action_type' => $action['action_type'],
                'payload' => $payload ?: null,
                'order' => $index,
            ]);
        }
    }

    protected function formOptions(): array
    {
        return [
            'fields' => ConditionField::cases(),
            'operators' => ConditionOperator::cases(),
            'booleanOperators' => BooleanOperator::cases(),
            'actionTypes' => AiAction::cases(),
            'tones' => Tone::cases(),
            'escalationTargets' => EscalationTarget::cases(),
            'replyTemplates' => ReplyTemplate::query()->orderBy('name')->get(),
        ];
    }
}
