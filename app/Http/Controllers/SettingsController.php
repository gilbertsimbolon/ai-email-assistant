<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Tampilkan status koneksi Gmail milik user yang login.
     */
    public function index(Request $request)
    {
        $gmailAccounts = $request->user()->gmailAccounts()->latest()->get();

        return view('settings.index', compact('gmailAccounts'));
    }
}
