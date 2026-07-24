<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Reject webhook requests that don't present the shared secret.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webhook.secret');
        $provided = $request->header('X-Webhook-Secret');

        if (blank($expected) || !is_string($provided) || !hash_equals($expected, $provided)) {

            Log::warning('Webhook rejected: invalid or missing secret', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
