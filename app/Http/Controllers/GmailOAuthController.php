<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Services\Gmail\GmailAuthService;
use App\Services\Gmail\GmailConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GmailOAuthController extends Controller
{
    public function __construct(
        protected GmailAuthService $gmailAuth,
        protected GmailConfigurationService $gmailConfig,
    ) {
    }

    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (!$this->gmailConfig->isEnabled()) {
            return redirect()->route('settings.index')
                ->withErrors(['gmail' => 'Integrasi Gmail sedang dinonaktifkan oleh administrator.']);
        }

        $state = Str::random(40);
        $request->session()->put('gmail_oauth_state', $state);

        return redirect()->away($this->gmailAuth->buildAuthorizationUrl($state));
    }

    /**
     * Handle Google's OAuth callback: verify state, exchange the code, and
     * persist the connected GmailAccount for the logged-in user.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('gmail_oauth_state');

        if ($request->filled('error')) {
            return redirect()->route('settings.index')
                ->withErrors(['gmail' => 'Koneksi Gmail dibatalkan.']);
        }

        if (blank($expectedState) || $request->query('state') !== $expectedState) {
            return redirect()->route('settings.index')
                ->withErrors(['gmail' => 'Sesi OAuth tidak valid, silakan coba lagi.']);
        }

        if (blank($request->query('code'))) {
            return redirect()->route('settings.index')
                ->withErrors(['gmail' => 'Google tidak mengembalikan kode otorisasi.']);
        }

        try {
            $this->gmailAuth->connectAccount($request->user(), $request->query('code'));
        } catch (Throwable $e) {
            Log::error('Gmail OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('settings.index')
                ->withErrors(['gmail' => 'Gagal menghubungkan akun Gmail: '.$e->getMessage()]);
        }

        return redirect()->route('settings.index')->with('success', 'Akun Gmail berhasil terhubung.');
    }

    /**
     * Disconnect a connected Gmail account.
     */
    public function disconnect(Request $request, GmailAccount $gmailAccount): RedirectResponse
    {
        abort_unless($gmailAccount->user_id === $request->user()->id, 403);

        $this->gmailAuth->disconnect($gmailAccount);

        return redirect()->route('settings.index')->with('success', 'Akun Gmail telah diputuskan.');
    }
}
