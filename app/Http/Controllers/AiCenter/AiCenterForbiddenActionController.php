<?php

namespace App\Http\Controllers\AiCenter;

use App\Http\Controllers\Controller;
use App\Models\AiCenter\ForbiddenAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiCenterForbiddenActionController extends Controller
{
    public function index(): View
    {
        return view('ai-center.forbidden-actions.index', [
            'forbiddenActions' => ForbiddenAction::query()->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255', 'unique:forbidden_actions,label'],
            'description' => ['nullable', 'string'],
        ]);

        ForbiddenAction::create($validated);

        return back()->with('success', 'Forbidden action berhasil ditambahkan.');
    }

    public function destroy(ForbiddenAction $forbiddenAction): RedirectResponse
    {
        $forbiddenAction->delete();

        return back()->with('success', 'Forbidden action berhasil dihapus.');
    }
}
