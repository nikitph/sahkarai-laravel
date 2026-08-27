<?php

namespace App\Services\Ingestion;

final class AspNetPage
{
    /** @return array<string, string> */
    public static function hiddenFields(string $html): array
    {
        $fields = [];
        preg_match_all('/<input\b[^>]*>/i', $html, $inputs);
        foreach ($inputs[0] as $input) {
            if (! preg_match('/\btype=["\']hidden["\']/i', $input) || ! preg_match('/\bname=["\']([^"\']+)/i', $input, $name)) {
                continue;
            }
            preg_match('/\bvalue=["\']([^"\']*)/i', $input, $value);
            $fields[html_entity_decode($name[1], ENT_QUOTES | ENT_HTML5)] = html_entity_decode($value[1] ?? '', ENT_QUOTES | ENT_HTML5);
        }

        return $fields;
    }

    public static function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }
}
