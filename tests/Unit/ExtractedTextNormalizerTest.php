<?php

namespace Tests\Unit;

use App\Support\Documents\ExtractedTextNormalizer;
use PHPUnit\Framework\TestCase;

class ExtractedTextNormalizerTest extends TestCase
{
    public function test_it_repairs_malformed_utf8_and_removes_embedded_pdf_control_bytes(): void
    {
        $raw = "Reserve Bank\0 of India\n\n\nFraud \xC3\x28 directions\x02";

        $normalized = (new ExtractedTextNormalizer)->normalize($raw);

        $this->assertTrue(mb_check_encoding($normalized, 'UTF-8'));
        $this->assertSame(0, preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $normalized));
        $this->assertStringContainsString('Reserve Bank of India', $normalized);
        $this->assertNotFalse(json_encode(['prompt' => $normalized]));
    }
}
