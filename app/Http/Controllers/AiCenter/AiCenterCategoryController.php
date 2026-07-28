<?php

namespace App\Http\Controllers\AiCenter;

use App\Http\Controllers\Controller;
use App\Models\AiCenter\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Categories are created inline from the Intent/SOP forms (a plain "add new
 * category" text input + submit) rather than having their own CRUD page —
 * they're a lightweight grouping label shared by Intent and Sop.
 */
class AiCenterCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        Category::create($validated);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }
}
