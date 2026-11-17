<?php

namespace App\Http\Controllers;

use App\Generations\GenerationWebhooks;
use App\Pixelforge\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Pixelforge's webhook endpoint. It sits outside the web middleware (no session, no CSRF),
 * verifies the signature over the raw body, and processes the event in the request (ADR-0003).
 */
class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, GenerationWebhooks $webhooks): Response
    {
        $body = $request->getContent();

        if (! WebhookSignature::isValid(
            $request->header('Pixelforge-Signature'),
            $body,
            (string) config('services.pixelforge.webhook_secret'),
            now()->getTimestamp(),
        )) {
            Log::warning('Pixelforge webhook rejected: bad signature', ['event' => $request->header('Pixelforge-Event-Id')]);

            return response('Invalid signature', 400);
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            Log::warning('Pixelforge webhook rejected: malformed event');

            return response('Malformed event', 400);
        }

        $webhooks->handle($event);

        return response('OK', 200);
    }
}
