<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\PriorityLevel;
use App\Enums\AiCenter\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\Category;
use App\Models\AiCenter\Intent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCenterIntentController extends Controller
{
    public function index(): View
    {
        return view('ai-center.intents.index', [
            'intents' => Intent::query()->with('category')->withCount(['keywords', 'sops'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ai-center.intents.form', [
            'intent' => new Intent(),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $intent = Intent::create($validated);

        $this->syncKeywords($intent, $request->input('keywords', []));
        $this->syncExamples($intent, $request->input('examples', []));

        return redirect()->route('ai-center.intents.index')->with('success', 'Intent berhasil dibuat.');
    }

    public function edit(Intent $intent): View
    {
        $intent->load('keywords', 'examples');

        return view('ai-center.intents.form', [
            'intent' => $intent,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Intent $intent): RedirectResponse
    {
        $validated = $this->validated($request, $intent);

        $intent->update($validated);

        $this->syncKeywords($intent, $request->input('keywords', []));
        $this->syncExamples($intent, $request->input('examples', []));

        return redirect()->route('ai-center.intents.index')->with('success', 'Intent berhasil diperbarui.');
    }

    public function destroy(Intent $intent): RedirectResponse
    {
        $intent->delete();

        return back()->with('success', 'Intent berhasil dihapus.');
    }

    protected function validated(Request $request, ?Intent $intent = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('intents', 'name')->ignore($intent?->id)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'priority' => ['required', Rule::enum(PriorityLevel::class)],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'description' => ['nullable', 'string'],
        ]);
    }

    protected function syncKeywords(Intent $intent, array $keywords): void
    {
        $intent->keywords()->delete();

        foreach (array_filter(array_map('trim', $keywords)) as $keyword) {
            $intent->keywords()->create(['keyword' => $keyword]);
        }
    }

    protected function syncExamples(Intent $intent, array $examples): void
    {
        $intent->examples()->delete();

        foreach (array_filter(array_map('trim', $examples)) as $example) {
            $intent->examples()->create(['example_text' => $example]);
        }
    }
}
