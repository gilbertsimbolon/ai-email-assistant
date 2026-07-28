<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\KnowledgeBaseType;
use App\Enums\AiCenter\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\KnowledgeBase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCenterKnowledgeBaseController extends Controller
{
    public function index(): View
    {
        return view('ai-center.knowledge-bases.index', [
            'knowledgeBases' => KnowledgeBase::query()->orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ai-center.knowledge-bases.form', ['knowledgeBase' => new KnowledgeBase()]);
    }

    public function store(Request $request): RedirectResponse
    {
        KnowledgeBase::create($this->validated($request));

        return redirect()->route('ai-center.knowledge-bases.index')->with('success', 'Knowledge Base berhasil dibuat.');
    }

    public function edit(KnowledgeBase $knowledgeBase): View
    {
        return view('ai-center.knowledge-bases.form', ['knowledgeBase' => $knowledgeBase]);
    }

    public function update(Request $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $knowledgeBase->update($this->validated($request));

        return redirect()->route('ai-center.knowledge-bases.index')->with('success', 'Knowledge Base berhasil diperbarui.');
    }

    public function destroy(KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $knowledgeBase->delete();

        return back()->with('success', 'Knowledge Base berhasil dihapus.');
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(KnowledgeBaseType::class)],
            'content' => ['required', 'string'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['sort_order'] ??= 0;

        return $validated;
    }
}
