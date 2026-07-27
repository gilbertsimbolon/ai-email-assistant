<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGmailSettingsRequest;
use App\Services\Gmail\GmailApiService;
use App\Services\Gmail\GmailConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets an administrator manage the global Gmail OAuth configuration
 * (client id/secret/redirect uri) from the Settings page instead of the
 * .env file. Restricted to admins by the 'admin' route middleware.
 */
class GmailSettingsController extends Controller
{
    public function __construct(
        protected GmailConfigurationService $gmailConfig,
        protected GmailApiService $gmailApi,
    ) {
    }

    public function index(): View
    {
        return view('settings.gmail-config', [
            'clientId' => $this->gmailConfig->getClientId(),
            'redirectUri' => $this->gmailConfig->getRedirectUri(),
            'enabled' => $this->gmailConfig->isEnabled(),
            'hasClientSecret' => filled($this->gmailConfig->getClientSecret()),
            'source' => $this->gmailConfig->source(),
        ]);
    }

    public function update(UpdateGmailSettingsRequest $request): RedirectResponse
    {
        $this->gmailConfig->save($request->toSettingsData());

        return redirect()->route('settings.gmail-config.index')
            ->with('success', 'Konfigurasi Gmail berhasil disimpan.');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:255'],
        ]);

        // An admin testing an unchanged secret won't have retyped it, so
        // fall back to the currently saved one — the same "blank means
        // keep it" rule the save form uses.
        $clientSecret = filled($validated['client_secret'] ?? null)
            ? $validated['client_secret']
            : $this->gmailConfig->getClientSecret();

        if (blank($clientSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Client Secret belum diisi.',
            ], 422);
        }

        $result = $this->gmailApi->testCredentials(
            $validated['client_id'],
            $clientSecret,
            $validated['redirect_uri'],
        );

        return response()->json($result);
    }
}
