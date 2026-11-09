<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta's webhook (Fase 4d). Configure in the Meta app:
 *   Callback URL: https://<api>/api/v1/webhooks/whatsapp
 *   Verify token: WHATSAPP_VERIFY_TOKEN · fields: messages
 */
class WhatsAppWebhookController extends Controller
{
    /** Subscription handshake: Meta sends hub.challenge and expects it back. */
    public function verify(Request $request): Response
    {
        $token = config('whatsapp.meta.verify_token');

        abort_unless(
            $request->query('hub_mode') === 'subscribe' && filled($token) && hash_equals((string) $token, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Every notification is signed by Meta (X-Hub-Signature-256 = HMAC-SHA256
     * of the raw body with the app secret). Unsigned or wrongly signed → 401.
     * Processing goes to the queue: Meta wants a fast 200.
     */
    public function receive(Request $request): JsonResponse
    {
        $secret = (string) config('whatsapp.meta.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        abort_if($secret === '' || ! hash_equals($expected, $signature), 401, 'Firma inválida.');

        ProcessWhatsAppWebhook::dispatch((array) $request->json()->all());

        return response()->json(['ok' => true]);
    }
}
