<?php

namespace App\Services\Archive;

use App\Models\RegulatoryDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ArchiveSearch
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RegulatoryDocument>
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $paginator = RegulatoryDocument::query()
            ->with(['latestVersion.interpretation'])
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['document_type'] ?? null, fn (Builder $query, string $type) => $query->where('document_type', $type))
            ->when($filters['applicability'] ?? null, fn (Builder $query, string $value) => $query->whereJsonContains('applicability_tags', $value))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '<=', $date))
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $quoted = str_starts_with(trim($term), '"') && str_ends_with(trim($term), '"');
                $terms = $quoted ? [trim($term, ' "')] : (preg_split('/\s+/', trim($term)) ?: []);
                foreach ($terms as $word) {
                    $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $word).'%';
                    $query->where(function (Builder $query) use ($operator, $needle): void {
                        $query->where('title', $operator, $needle)
                            ->orWhereHas('latestVersion', fn (Builder $version) => $version->where('extracted_text', $operator, $needle))
                            ->orWhereHas('latestVersion.interpretation', fn (Builder $interpretation) => $interpretation
                                ->whereRaw('CAST(locale_payloads AS TEXT) '.($operator === 'ilike' ? 'ILIKE' : 'LIKE').' ?', [$needle]));
                    });
                }
            })
            ->when(
                ($filters['sort'] ?? 'newest') === 'title',
                fn (Builder $query) => $query->orderBy('title')->orderBy('id'),
                fn (Builder $query) => $query->orderByDesc('published_at')->orderByDesc('id'),
            )
            ->paginate(20)
            ->withQueryString();

        if ($filters['q'] ?? null) {
            $term = mb_strtolower(trim((string) $filters['q'], ' "'));
            $paginator->setCollection($paginator->getCollection()->sortByDesc(
                fn (RegulatoryDocument $document) => $this->englishScore($document, $term),
            )->values());
        }

        return $paginator;
    }

    private function englishScore(RegulatoryDocument $document, string $term): int
    {
        $english = $document->latestVersion?->interpretation?->locale_payloads['en'] ?? [];
        $haystack = mb_strtolower($document->title.' '.($document->latestVersion->extracted_text ?? '').' '.json_encode($english, JSON_THROW_ON_ERROR));

        return str_contains($haystack, $term) ? 1 : 0;
    }
}
