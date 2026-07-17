<?php

namespace App\Actions\Interpretations;

use DateTimeImmutable;

class NormalizeInterpretationDates
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array
    {
        if (is_array($payload['deadlines'] ?? null)) {
            $payload['deadlines'] = array_map(function (mixed $deadline): mixed {
                if (is_array($deadline) && is_string($deadline['due_date'] ?? null)) {
                    $deadline['due_date'] = $this->normalize($deadline['due_date']);
                }

                return $deadline;
            }, $payload['deadlines']);
        }

        if (is_string($payload['effective_date'] ?? null)) {
            $payload['effective_date'] = $this->normalize($payload['effective_date']);
        }

        return $payload;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        foreach (['!d.m.Y', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }
}
