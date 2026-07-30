<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Admin-only account management, gated by the "manage users" permission
 * (see routes/web.php) — lets the Admin/Agent split from claude.txt's
 * Spatie Permission integration actually be assigned through the UI
 * instead of only via the seeder.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('roles')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $validated['role'] === 'Admin',
        ]);

        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $validated['role'] === 'Admin',
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Tidak dapat menghapus akun Anda sendiri.');

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
