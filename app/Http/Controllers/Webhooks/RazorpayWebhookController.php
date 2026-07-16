<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ProcessRazorpayWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessRazorpayWebhook $process): JsonResponse
    {
        $body = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');
        $secret = (string) config('sahkarai.razorpay.webhook_secret');
        abort_if($secret === '', 503, 'Razorpay webhooks are not configured.');
        $expected = hash_hmac('sha256', $body, $secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Invalid webhook signature.');

        $payload = $request->json()->all();
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $body));
        $process->handle($eventId, $payload);

        return response()->json(['received' => true]);
    }
}
