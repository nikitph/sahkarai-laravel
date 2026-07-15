<?php

namespace App\Actions\Interpretations;

use App\Ai\Agents\RegulatoryInterpretationAgent;
use App\Enums\SupportedLocale;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class GenerateLocaleInterpretation
{
    /** @return array<string, mixed> */
    public function handle(DocumentVersion $version, SupportedLocale $locale): array
    {
        $response = RegulatoryInterpretationAgent::make()->prompt(
            "Write the {$locale->name} ({$locale->value}) interpretation of this document.\n\n{$version->sourceText()}",
            provider: config('sahkarai.ai.provider'),
            model: config('sahkarai.ai.interpretation_model'),
            timeout: 120,
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The AI provider did not return structured output.');
        }
        $payload = $response->structured;

        $validated = Validator::make($payload, [
            'locale' => ['required', 'in:'.$locale->value],
            'summary' => ['required', 'string'],
            'takeaways' => ['required', 'array', 'min:3', 'max:7'],
            'takeaways.*' => ['required', 'string'],
            'glossary' => ['sometimes', 'array'],
            'glossary.*.term' => ['required', 'string'],
            'glossary.*.definition' => ['required', 'string'],
            'deadlines' => ['present', 'array'],
            'deadlines.*.due_date' => ['required', 'date_format:Y-m-d'],
            'deadlines.*.description' => ['required', 'string'],
            'applicability_tags' => ['present', 'array', 'max:6'],
            'applicability_tags.*' => ['required', 'in:pacs,ucb,dccb,stcb,apex,generic'],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'document_type' => ['required', 'in:master_direction,circular,notification,press_release,faq,other'],
        ])->validate();
        $validated['glossary'] ??= [];

        $wordCount = count(preg_split('/\s+/u', trim($validated['summary']), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($wordCount < 150 || $wordCount > 300) {
            throw ValidationException::withMessages([
                'summary' => "The localized summary must contain 150 to 300 words; {$wordCount} returned.",
            ]);
        }

        if ($locale === SupportedLocale::English) {
            $tags = $validated['applicability_tags'];
            $version->document->update([
                'applicability' => $tags[0] ?? 'generic',
                'applicability_tags' => $tags,
                'effective_at' => $validated['effective_date'],
                'document_type' => $validated['document_type'],
            ]);
        }

        return $validated;
    }
}
