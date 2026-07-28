<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\AiAction;
use App\Enums\AiCenter\ConditionField;
use App\Enums\AiCenter\ConditionOperator;
use App\Enums\AiCenter\EdgeBranch;
use App\Enums\AiCenter\WorkflowNodeType;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\Workflow;
use App\Models\AiCenter\WorkflowNode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Add Step" builder for the Workflow Builder's structured step-list UI
 * (no drag-drop canvas). Each submit creates exactly one new node plus one
 * edge from an existing node to it — new nodes are never wired as the
 * source of an edge to an already-existing node, so the graph is acyclic by
 * construction.
 */
class AiCenterWorkflowNodeController extends Controller
{
    public function store(Request $request, Workflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'from_node_id' => ['required', 'exists:workflow_nodes,id'],
            'branch' => ['nullable', Rule::enum(EdgeBranch::class)],
            'type' => ['required', Rule::enum(WorkflowNodeType::class)],
            'label' => ['required', 'string', 'max:255'],
            'field' => ['required_if:type,condition', 'nullable', Rule::enum(ConditionField::class)],
            'operator' => ['required_if:type,condition', 'nullable', Rule::enum(ConditionOperator::class)],
            'value' => ['nullable', 'string', 'max:255'],
            'action_type' => ['required_if:type,action', 'nullable', Rule::enum(AiAction::class)],
        ]);

        $fromNode = WorkflowNode::query()->where('workflow_id', $workflow->id)->findOrFail($validated['from_node_id']);

        $branch = $this->resolveBranch($fromNode, $validated['branch'] ?? null);

        $config = match ($validated['type']) {
            WorkflowNodeType::Condition->value => [
                'field' => $validated['field'],
                'operator' => $validated['operator'],
                'value' => $validated['value'] ?? null,
            ],
            WorkflowNodeType::Action->value => ['action_type' => $validated['action_type']],
            default => null,
        };

        $node = $workflow->nodes()->create([
            'type' => $validated['type'],
            'label' => $validated['label'],
            'config' => $config,
            'order' => $workflow->nodes()->count(),
        ]);

        $workflow->edges()->create([
            'from_node_id' => $fromNode->id,
            'to_node_id' => $node->id,
            'branch' => $branch->value,
        ]);

        return redirect()->route('ai-center.workflows.edit', $workflow)->with('success', 'Step berhasil ditambahkan.');
    }

    public function destroy(Workflow $workflow, WorkflowNode $node): RedirectResponse
    {
        $maxOrder = $workflow->nodes()->max('order');

        abort_if($node->order != $maxOrder, 422, 'Hapus step paling akhir terlebih dahulu.');
        abort_if($node->type === WorkflowNodeType::Start, 422, 'Step Start tidak dapat dihapus.');

        $node->delete();

        return redirect()->route('ai-center.workflows.edit', $workflow)->with('success', 'Step berhasil dihapus.');
    }

    protected function resolveBranch(WorkflowNode $fromNode, ?string $requested): EdgeBranch
    {
        if ($fromNode->type !== WorkflowNodeType::Condition) {
            if ($fromNode->outgoingEdges()->exists()) {
                throw ValidationException::withMessages([
                    'from_node_id' => 'Step ini bukan Condition sehingga hanya boleh memiliki satu langkah berikutnya.',
                ]);
            }

            return EdgeBranch::Default;
        }

        $branch = EdgeBranch::tryFrom($requested ?? '') ?? null;

        if (! in_array($branch, [EdgeBranch::Yes, EdgeBranch::No], true)) {
            throw ValidationException::withMessages([
                'branch' => 'Pilih cabang Yes atau No untuk step Condition.',
            ]);
        }

        if ($fromNode->outgoingEdges()->where('branch', $branch->value)->exists()) {
            throw ValidationException::withMessages([
                'branch' => "Cabang {$branch->value} sudah memiliki langkah berikutnya.",
            ]);
        }

        return $branch;
    }
}
