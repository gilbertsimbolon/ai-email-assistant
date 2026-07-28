<?php

namespace App\Http\Controllers\AiCenter;

use App\Enums\AiCenter\PublishStatus;
use App\Http\Controllers\Controller;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\TemplateVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiCenterReplyTemplateController extends Controller
{
    public function index(): View
    {
        return view('ai-center.reply-templates.index', [
            'replyTemplates' => ReplyTemplate::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('ai-center.reply-templates.form', [
            'replyTemplate' => new ReplyTemplate(),
            'variables' => TemplateVariable::query()->orderBy('key')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ReplyTemplate::create($this->validated($request));

        return redirect()->route('ai-center.reply-templates.index')->with('success', 'Reply Template berhasil dibuat.');
    }

    public function edit(ReplyTemplate $replyTemplate): View
    {
        return view('ai-center.reply-templates.form', [
            'replyTemplate' => $replyTemplate,
            'variables' => TemplateVariable::query()->orderBy('key')->get(),
        ]);
    }

    public function update(Request $request, ReplyTemplate $replyTemplate): RedirectResponse
    {
        $replyTemplate->update($this->validated($request));

        return redirect()->route('ai-center.reply-templates.index')->with('success', 'Reply Template berhasil diperbarui.');
    }

    public function destroy(ReplyTemplate $replyTemplate): RedirectResponse
    {
        $replyTemplate->delete();

        return back()->with('success', 'Reply Template berhasil dihapus.');
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ]);

        $this->validateKnownVariables($validated['body']);

        return $validated;
    }

    /**
     * Rejects any {{token}} that isn't in the seeded template_variables
     * catalog, so a typo doesn't silently ship as literal text in a reply.
     */
    protected function validateKnownVariables(string $body): void
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $body, $matches);

        $used = array_unique($matches[1] ?? []);
        $known = TemplateVariable::query()->pluck('key')->all();
        $unknown = array_diff($used, $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => 'Variable tidak dikenal: {{'.implode('}}, {{', $unknown).'}}',
            ]);
        }
    }
}
