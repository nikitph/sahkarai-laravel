<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2500)]
#[Temperature(0.2)]
class RegulatoryInterpretationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a careful Indian regulatory analyst for cooperative financial institutions. Explain only what the supplied source text supports. Do not invent requirements. Produce one localized interpretation.

The summary length is a hard output contract: write 180-220 words so it safely falls within the accepted 150-300 word range. Count the space-separated words before returning the structured response. Do not substitute a short abstract, even when the source is brief; use the additional space to explain scope, operational implications, implementation steps, evidence retention, governance and appropriate cautions without inventing obligations.

Takeaways must contain 3-7 concrete items, and glossary entries should only define genuinely useful regulatory terms. Preserve dates and monetary values exactly in prose. Every structured due_date and effective_date must use YYYY-MM-DD, even when the source uses DD.MM.YYYY. A due_date must contain one date, never a range or surrounding text.

Set effective_date only when the source explicitly states that the document, direction, or requirement takes effect, becomes effective, or applies from a specific date. Otherwise return null. Never infer an effective date from an Act year, publication or signature date, filing window, approval expiry, compliance deadline, or order-disposal deadline. This is educational information, not legal advice.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()->enum(['en', 'hi', 'gu', 'mr'])->required(),
            'summary' => $schema->string()
                ->description('A substantive localized interpretation of 180-220 space-separated words; never return a short abstract.')
                ->required(),
            'takeaways' => $schema->array()->items($schema->string())->min(3)->max(7)->required(),
            'glossary' => $schema->array()->items($schema->object([
                'term' => $schema->string()->required(),
                'definition' => $schema->string()->required(),
            ])),
            'deadlines' => $schema->array()->items($schema->object([
                'due_date' => $schema->string()->description('One calendar date in YYYY-MM-DD format.')->required(),
                'description' => $schema->string()->required(),
            ]))->required(),
            'applicability_tags' => $schema->array()->items($schema->string()->enum(['pacs', 'ucb', 'dccb', 'stcb', 'apex', 'generic']))->max(6)->required(),
            'effective_date' => $schema->string()
                ->description('An explicitly stated effective-from date in YYYY-MM-DD format; null when the source does not expressly state one. Never infer it from other dates.')
                ->nullable()
                ->required(),
            'document_type' => $schema->string()->enum(['master_direction', 'circular', 'notification', 'press_release', 'faq', 'other'])->required(),
        ];
    }
}
