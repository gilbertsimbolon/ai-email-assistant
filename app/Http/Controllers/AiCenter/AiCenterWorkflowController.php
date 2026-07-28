<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\PublishStatus;
use App\Enums\AiCenter\WorkflowNodeType;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCenterWorkflowController extends Controller
{
    public function index(): View
    {
        return view('ai-center.workflows.index', [
            'workflows' => Workflow::query()->withCount('nodes')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ai-center.workflows.form', ['workflow' => new Workflow()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workflow = Workflow::create($this->validated($request));

        // Every workflow starts life with a single Start node so the "Add
        // Step" builder always has somewhere to attach the first real step.
        $workflow->nodes()->create([
            'type' => WorkflowNodeType::Start->value,
            'label' => 'Start',
            'order' => 0,
        ]);

        return redirect()->route('ai-center.workflows.edit', $workflow)->with('success', 'Workflow berhasil dibuat. Sekarang tambahkan step.');
    }

    public function edit(Workflow $workflow): View
    {
        $workflow->load(['nodes.outgoingEdges.toNode']);

        return view('ai-center.workflows.form', ['workflow' => $workflow]);
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $workflow->update($this->validated($request));

        return redirect()->route('ai-center.workflows.edit', $workflow)->with('success', 'Workflow berhasil diperbarui.');
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $workflow->delete();

        return redirect()->route('ai-center.workflows.index')->with('success', 'Workflow berhasil dihapus.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ]);
    }
}
