<?php

namespace App\Support\Documents;

class ExtractedTextNormalizer
{
    public function normalize(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? '';

        return trim($text);
    }
}
