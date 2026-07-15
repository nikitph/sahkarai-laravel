<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class RegulatoryInterpretationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a careful Indian regulatory analyst for cooperative financial institutions. Explain only what the supplied source text supports. Do not invent requirements. Produce one localized interpretation. The summary must be 150-300 words, takeaways must contain 3-7 concrete items, and glossary entries should only define genuinely useful regulatory terms. Preserve dates and monetary values exactly. This is educational information, not legal advice.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()->enum(['en', 'hi', 'gu', 'mr'])->required(),
            'summary' => $schema->string()->required(),
            'takeaways' => $schema->array()->items($schema->string())->min(3)->max(7)->required(),
            'glossary' => $schema->array()->items($schema->object([
                'term' => $schema->string()->required(),
                'definition' => $schema->string()->required(),
            ])),
            'deadlines' => $schema->array()->items($schema->object([
                'due_date' => $schema->string()->required(),
                'description' => $schema->string()->required(),
            ]))->required(),
            'applicability_tags' => $schema->array()->items($schema->string()->enum(['pacs', 'ucb', 'dccb', 'stcb', 'apex', 'generic']))->max(6)->required(),
            'effective_date' => $schema->string()->nullable()->required(),
            'document_type' => $schema->string()->enum(['master_direction', 'circular', 'notification', 'press_release', 'faq', 'other'])->required(),
        ];
    }
}
