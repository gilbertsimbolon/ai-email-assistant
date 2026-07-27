<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    // fungsi index
    public function index()
    {
        return view('profile.index');
    }

    // fungsi update data
    public function update(UpdateProfileRequest $request)
    {
        // update data user
        $user = Auth::user();
        $user->name = $request->name ?? $user->name;
        $user->email = $request->filled('email') ? $request->email : $user->email;

        $user->save();

        return redirect()->back()->with('success', 'Profil berhasil diperbarui.');
    }

    // fungsi ganti password
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = Auth::user();

        // validasi password lama
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama tidak sesuai.'
            ]);
        }

        // validasi agar password baru tidak sama dengan password lama
        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => 'Password baru tidak boleh sama dengan password lama.'
            ]);
        }

        // simpan password baru
        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('success', 'Password berhasil diubah.');
    }
}
