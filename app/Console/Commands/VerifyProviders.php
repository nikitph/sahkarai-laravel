<?php

namespace App\Console\Commands;

use App\Ai\Agents\ProviderProbeAgent;
use App\Ai\Agents\RegulatoryInterpretationAgent;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class VerifyProviders extends Command
{
    protected $signature = 'sahkarai:providers:verify
        {--only= : Verify only ai or razorpay}';

    protected $description = 'Run non-destructive live conformance checks against DeepSeek and Razorpay';

    public function handle(): int
    {
        $only = (string) $this->option('only');
        if ($only !== '' && ! in_array($only, ['ai', 'razorpay'], true)) {
            $this->components->error('The --only option must be ai or razorpay.');

            return self::INVALID;
        }

        $checks = $only === '' ? ['ai', 'razorpay'] : [$only];
        $failed = false;
        foreach ($checks as $check) {
            try {
                $check === 'ai' ? $this->verifyAi() : $this->verifyRazorpay();
            } catch (Throwable $exception) {
                $failed = true;
                $this->components->error(ucfirst($check).' verification failed: '.$exception->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function verifyAi(): void
    {
        $provider = (string) config('sahkarai.ai.provider');
        $model = (string) config('sahkarai.ai.interpretation_model');
        $key = (string) config("ai.providers.{$provider}.key");
        if ($key === '') {
            throw new RuntimeException("No API key is configured for the {$provider} provider.");
        }

        $response = RegulatoryInterpretationAgent::make()->prompt(
            <<<'PROMPT'
Write an English (en) interpretation of this synthetic conformance document. The summary must contain 180-220 space-separated words. Count it before returning the structured response; a shorter abstract fails conformance.

The regulator directs urban cooperative banks to review their cyber incident response plan, assign an accountable officer, conduct one annual exercise, and retain the exercise report. The direction takes effect on 2026-09-01. This synthetic text is used only to verify the configured AI provider and must not be stored as regulatory content.
PROMPT,
            provider: $provider,
            model: $model,
            timeout: 120,
        );
        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The provider did not return Laravel AI SDK structured output.');
        }

        $payload = Validator::make($response->structured, [
            'locale' => ['required', 'in:en'],
            'summary' => ['required', 'string'],
            'takeaways' => ['required', 'array', 'min:3', 'max:7'],
            'glossary' => ['sometimes', 'array'],
            'deadlines' => ['present', 'array'],
            'applicability_tags' => ['present', 'array'],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'document_type' => ['required', 'string'],
        ])->validate();
        $words = count(preg_split('/\s+/u', trim($payload['summary']), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($words < 150 || $words > 300) {
            throw new RuntimeException("The structured summary contained {$words} words; expected 150-300.");
        }

        $stream = ProviderProbeAgent::make()->stream(
            'Stream a one-sentence confirmation that provider conformance is working.',
            provider: $provider,
            model: (string) config('sahkarai.ai.chat_model'),
            timeout: 120,
        );
        $eventCount = 0;
        foreach ($stream as $event) {
            $eventCount++;
        }
        if ($eventCount === 0 || blank($stream->text)) {
            throw new RuntimeException('The provider returned no streaming text events.');
        }

        $this->components->info("AI structured output and streaming verified through Laravel AI SDK ({$response->meta->provider} / {$response->meta->model}).");
    }

    private function verifyRazorpay(): void
    {
        $key = (string) config('sahkarai.razorpay.key_id');
        $secret = (string) config('sahkarai.razorpay.key_secret');
        $webhookSecret = (string) config('sahkarai.razorpay.webhook_secret');
        if ($key === '' || $secret === '' || $webhookSecret === '') {
            throw new RuntimeException('Razorpay key ID, key secret, and webhook secret are required.');
        }

        $client = Http::baseUrl((string) config('sahkarai.razorpay.base_url'))
            ->withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);

        $this->verifyPlan($client, 'tier_1');
        $this->verifyPlan($client, 'tier_2');
        $this->verifyPlan($client, 'tier_3');
        $this->components->info('Razorpay credentials and all three INR monthly plans verified read-only.');
    }

    private function verifyPlan(PendingRequest $client, string $tier): void
    {
        $planId = (string) config("sahkarai.razorpay.plans.{$tier}");
        if ($planId === '') {
            throw new RuntimeException("No Razorpay plan ID is configured for {$tier}.");
        }

        $plan = $client->get("/plans/{$planId}")->throw()->json();
        $expectedAmount = (int) config("sahkarai.tiers.{$tier}.monthly_price");
        if (($plan['id'] ?? null) !== $planId
            || ($plan['period'] ?? null) !== 'monthly'
            || (int) ($plan['interval'] ?? 0) !== 1
            || ($plan['item']['currency'] ?? null) !== 'INR'
            || (int) ($plan['item']['amount'] ?? 0) !== $expectedAmount) {
            throw new RuntimeException("Razorpay plan {$planId} does not match the configured {$tier} contract.");
        }
    }
}
