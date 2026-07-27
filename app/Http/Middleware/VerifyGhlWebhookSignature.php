<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyGhlWebhookSignature
{
    /**
     * Reject GHL conversation webhook requests that don't present the shared
     * secret configured as a custom header on the sending GHL Workflow's
     * "Webhook" action.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webhook.ghl_secret');
        $provided = $request->header('X-GHL-Webhook-Secret');

        if (blank($expected) || !is_string($provided) || !hash_equals($expected, $provided)) {
            Log::warning('GHL webhook rejected: invalid or missing secret', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
