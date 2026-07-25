<?php

namespace App\Support\Documents;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Throwable;

class ReadablePdf
{
    public function validate(string $contents): void
    {
        if (! str_starts_with(ltrim($contents), '%PDF-')) {
            throw ValidationException::withMessages(['document' => 'The uploaded file is not a valid PDF.']);
        }

        try {
            $text = trim((new Parser)->parseContent($contents)->getText());
        } catch (Throwable) {
            throw ValidationException::withMessages(['document' => 'The PDF is damaged, encrypted, or cannot be read.']);
        }

        if ($text === '') {
            throw ValidationException::withMessages(['document' => 'No readable text was found in the PDF. Scanned image-only PDFs are not yet supported.']);
        }
    }
}
