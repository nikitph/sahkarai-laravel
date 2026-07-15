<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\Interpretation;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InterpretationExportController extends Controller
{
    public function __invoke(Request $request, Interpretation $interpretation, string $format): Response
    {
        abort_unless($request->user()->tier->canExportDocuments() && ! $request->user()->isAdmin(), 403);
        abort_unless(in_array($format, ['md', 'pdf'], true), 422, 'export_format_invalid');
        $interpretation->load('version.document');
        $payload = $interpretation->payloadFor($request->user()->locale->value);
        abort_unless((bool) $payload, 404, 'Interpretation not available for this document.');
        $data = ['document' => $interpretation->version->document, 'interpretation' => $payload, 'meta' => $interpretation];
        $name = "sahkarai-interpretation-{$interpretation->getKey()}";
        $markdown = view('exports.interpretation-markdown', $data)->render();

        if ($format === 'md') {
            return response($markdown, 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => "attachment; filename={$name}.md"]);
        }

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(view('exports.interpretation-pdf', $data)->render());
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename={$name}.pdf"]);
    }
}
