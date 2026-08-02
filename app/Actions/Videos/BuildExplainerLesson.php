<?php

namespace App\Actions\Videos;

use App\Models\DocumentVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class BuildExplainerLesson
{
    /** @return array<string, mixed> */
    public function handle(DocumentVersion $version, string $locale = 'en'): array
    {
        $version->loadMissing(['document', 'interpretation']);
        $interpretation = $version->interpretation;
        if ($interpretation === null || ! in_array($interpretation->status, ['published', 'partial'], true)) {
            throw new RuntimeException('A published interpretation is required before video generation.');
        }

        $payloads = $interpretation->locale_payloads ?? [];
        $resolvedLocale = isset($payloads[$locale])
            ? $locale
            : (isset($payloads['en']) ? 'en' : (string) array_key_first($payloads));
        $payload = $resolvedLocale !== '' ? $interpretation->payloadFor($resolvedLocale) : null;
        if ($payload === null) {
            throw new RuntimeException('A published interpretation is required before video generation.');
        }

        $title = $version->document->title;
        $takeaways = array_values(array_filter(Arr::wrap($payload['takeaways'] ?? []), 'is_string'));
        $visualTakeaways = collect($takeaways)->map(fn (string $takeaway): string => Str::limit($takeaway, 140))->all();
        $deadlines = array_values(array_filter(Arr::wrap($payload['deadlines'] ?? []), 'is_array'));
        $beats = [
            $this->beat('opening', 0, 'Frame the document and its purpose.', "This explainer covers {$title}.", 'title_card', 'canvas_full', [
                'type' => 'title',
                'title' => Str::limit($title, 120),
                'subtitle' => 'A plain-language SahkarAI explainer based on the published interpretation.',
            ]),
            $this->beat('summary', 1, 'Explain the scope and practical meaning.', (string) $payload['summary'], 'concept_diagram', 'canvas_full', [
                'type' => 'concept',
                'heading' => 'What this document means',
                'body' => Str::limit((string) $payload['summary'], 320),
                'labels' => collect($takeaways)->take(3)->map(fn (string $takeaway): string => Str::limit($takeaway, 48))->all(),
            ]),
        ];

        if ($takeaways !== []) {
            $beats[] = $this->beat('takeaways', count($beats), 'Turn the interpretation into an actionable checklist.', $this->numberedNarration($takeaways), 'recap', 'recap_card', [
                'type' => 'recap',
                'points' => array_slice($visualTakeaways, 0, 3),
            ]);
        }

        if ($deadlines !== []) {
            $steps = collect($deadlines)->take(3)->map(fn (array $deadline): string => Str::limit(trim(($deadline['due_date'] ?? '').' — '.($deadline['description'] ?? '')), 170))->all();
            $beats[] = $this->beat('deadlines', count($beats), 'Preserve the exact compliance dates.', 'Important dates. '.implode(' ', $steps), 'data_visualization', 'canvas_full', [
                'type' => 'equation_derivation',
                'steps' => $steps,
                'transformations' => array_fill(0, count($steps), 'Recorded from the published interpretation'),
            ]);
        }

        $recap = array_slice($takeaways, 0, 3);
        if ($recap !== []) {
            $beats[] = $this->beat('close', count($beats), 'Close with the most important actions.', 'To recap. '.$this->numberedNarration($recap), 'recap', 'recap_card', [
                'type' => 'recap',
                'points' => array_slice($visualTakeaways, 0, 3),
            ]);
        }

        return [
            'version' => '1.0-no-avatar',
            'id' => "sahkar-document-{$version->getKey()}",
            'title' => $title,
            'subject' => str_replace('_', ' ', $version->document->document_type->value),
            'gradeLevel' => 'Cooperative financial institutions',
            'language' => $this->language($locale),
            'learningObjectives' => collect($beats)->map(fn (array $beat): array => [
                'id' => 'objective-'.$beat['id'],
                'description' => $beat['learningGoal'],
            ])->all(),
            'prerequisites' => [],
            'estimatedDurationMs' => collect($beats)->sum(fn (array $beat): int => $beat['narration']['expectedDurationMs']),
            'presentation' => ['branding' => 'acharya', 'headerLabel' => 'SAHKARAI EXPLAINER'],
            'narrationProfile' => [
                'provider' => config('sahkarai.video.narration_driver'),
                'voiceId' => config('sahkarai.video.elevenlabs.voice_id'),
                'modelId' => config('sahkarai.video.elevenlabs.model_id'),
                'language' => $this->language($locale),
                'normalizationVersion' => 'sahkarai/1.0.0',
            ],
            'beats' => $beats,
            'metadata' => [
                'documentVersionId' => $version->getKey(),
                'interpretationId' => $interpretation->getKey(),
                'interpretationPromptVersion' => $interpretation->prompt_version,
                'locale' => $resolvedLocale,
                'sourceTitle' => $title,
                'referenceNumber' => $version->document->reference_number,
                'disclaimer' => 'Educational summary only; not legal or financial advice.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $visualSpec
     * @return array<string, mixed>
     */
    private function beat(string $id, int $order, string $goal, string $narration, string $intent, string $layout, array $visualSpec): array
    {
        $words = count(preg_split('/\s+/u', trim($narration), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return [
            'id' => $id,
            'order' => $order,
            'learningGoal' => $goal,
            'narration' => [
                'text' => $narration,
                'exact' => true,
                'expectedDurationMs' => max(5000, $words * 360),
                'cues' => [],
            ],
            'delivery' => ['emotion' => 'serious', 'pace' => 'measured', 'intensity' => 0.35],
            'visualIntent' => $intent,
            'layout' => $layout,
            'visualSpec' => $visualSpec,
            'constraints' => ['exactnessRequired' => true, 'maxDurationMs' => max(8000, $words * 450), 'allowComparisonLayout' => false, 'forbiddenRenderers' => []],
            'rendererHint' => ['preferred' => 'hyperframes', 'allowed' => ['hyperframes'], 'qualityTier' => 'premium'],
            'binduPlan' => ['mode' => $id === 'close' ? 'underline' : 'highlight', 'returnToIdle' => true],
            'transitionIn' => ['type' => 'crossfade', 'durationMs' => 320],
            'transitionOut' => ['type' => 'crossfade', 'durationMs' => 320],
        ];
    }

    /** @param array<int, string> $items */
    private function numberedNarration(array $items): string
    {
        return collect($items)->values()->map(fn (string $item, int $index): string => 'Point '.($index + 1).': '.$item)->implode(' ');
    }

    private function language(string $locale): string
    {
        return match ($locale) {
            'hi' => 'hi-IN',
            'gu' => 'gu-IN',
            'mr' => 'mr-IN',
            default => 'en-IN',
        };
    }
}
