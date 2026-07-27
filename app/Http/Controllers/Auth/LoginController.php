<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // fungsi index
    public function index()
    {
        return view('auth.login');
    }

    // fungsi login, (store)
    public function store(LoginRequest $request)
    {
        $credentials = $request->validated();

        // proses autentikasi jika berhasil
        if (Auth::attempt($credentials, $request->remember)) {
            $request->session()->regenerate();

            return redirect()->route('dashboard');
        }

        // jika gagal
        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }
}
