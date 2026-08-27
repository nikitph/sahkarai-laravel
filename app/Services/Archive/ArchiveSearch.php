<?php

namespace App\Services\Archive;

use App\Models\RegulatoryDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ArchiveSearch
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RegulatoryDocument>
     */
    public function search(array $filters, User $user): LengthAwarePaginator
    {
        $query = RegulatoryDocument::query()
            ->visibleTo($user)
            ->with(['latestVersion.interpretation', 'latestPublishedVersion.interpretation'])
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['document_type'] ?? null, fn (Builder $query, string $type) => $query->where('document_type', $type))
            ->when($filters['applicability'] ?? null, fn (Builder $query, string $value) => $query->whereJsonContains('applicability_tags', $value))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('published_at', '<=', $date));

        $term = trim((string) ($filters['q'] ?? ''));
        $postgresSearch = $term !== '' && $user->isAdmin() && $query->getModel()->getConnection()->getDriverName() === 'pgsql';
        if ($postgresSearch) {
            $this->applyPostgresFullText($query, $term);
        } elseif ($term !== '') {
            $this->applyPortableSearch($query, $term, $user);
        }

        $paginator = $query
            ->when(
                ($filters['sort'] ?? 'newest') === 'title',
                fn (Builder $query) => $query->orderBy('title')->orderBy('id'),
                fn (Builder $query) => $query->orderByDesc('published_at')->orderByDesc('id'),
            )
            ->paginate(20)
            ->withQueryString();

        if ($term !== '' && ! $postgresSearch) {
            $normalizedTerm = mb_strtolower(trim($term, ' "'));
            $paginator->setCollection($paginator->getCollection()->sortByDesc(
                fn (RegulatoryDocument $document) => $this->englishScore($document, $normalizedTerm, $user),
            )->values());
        }

        return $paginator;
    }

    /** @param Builder<RegulatoryDocument> $query */
    private function applyPostgresFullText(Builder $query, string $term): void
    {
        $quoted = str_starts_with($term, '"') && str_ends_with($term, '"');
        $queryText = trim($term, ' "');
        $tsQuery = ($quoted ? "phraseto_tsquery('simple', ?)" : "plainto_tsquery('simple', ?)");
        $vector = <<<'SQL'
            setweight(to_tsvector('simple', coalesce(regulatory_documents.title, '')), 'A') ||
            setweight(to_tsvector('simple', coalesce((
                select concat_ws(' ', i.locale_payloads->'en'->>'summary', (i.locale_payloads->'en'->'takeaways')::text)
                from document_versions dv
                left join interpretations i on i.document_version_id = dv.id
                where dv.regulatory_document_id = regulatory_documents.id
                order by dv.version desc
                limit 1
            ), '')), 'A') ||
            setweight(to_tsvector('simple', coalesce((
                select dv.extracted_text
                from document_versions dv
                where dv.regulatory_document_id = regulatory_documents.id
                order by dv.version desc
                limit 1
            ), '')), 'B') ||
            setweight(to_tsvector('simple', coalesce((
                select i.locale_payloads::text
                from document_versions dv
                left join interpretations i on i.document_version_id = dv.id
                where dv.regulatory_document_id = regulatory_documents.id
                order by dv.version desc
                limit 1
            ), '')), 'D')
            SQL;

        $query->whereRaw("({$vector}) @@ {$tsQuery}", [$queryText]);
        $query->orderByRaw("ts_rank_cd(({$vector}), {$tsQuery}) desc", [$queryText]);
    }

    /** @param Builder<RegulatoryDocument> $query */
    private function applyPortableSearch(Builder $query, string $term, User $user): void
    {
        $quoted = str_starts_with($term, '"') && str_ends_with($term, '"');
        $terms = $quoted ? [trim($term, ' "')] : (preg_split('/\s+/', $term) ?: []);
        foreach ($terms as $word) {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $word).'%';
            $query->where(function (Builder $query) use ($needle, $user): void {
                $query->where('title', 'like', $needle);
                if ($user->isAdmin()) {
                    $query->orWhereHas('latestVersion', fn (Builder $version) => $version->where('extracted_text', 'like', $needle))
                        ->orWhereHas('latestVersion.interpretation', fn (Builder $interpretation) => $interpretation
                            ->whereRaw('CAST(locale_payloads AS TEXT) LIKE ?', [$needle]));

                    return;
                }

                $query->orWhere(function (Builder $owned) use ($needle, $user): void {
                    $owned->where('uploaded_by_user_id', $user->getKey())
                        ->where(function (Builder $content) use ($needle): void {
                            $content->whereHas('latestVersion', fn (Builder $version) => $version->where('extracted_text', 'like', $needle))
                                ->orWhereHas('latestVersion.interpretation', fn (Builder $interpretation) => $interpretation
                                    ->whereRaw('CAST(locale_payloads AS TEXT) LIKE ?', [$needle]));
                        });
                })->orWhere(function (Builder $platform) use ($needle): void {
                    $platform->whereNull('uploaded_by_user_id')
                        ->where(function (Builder $content) use ($needle): void {
                            $content->whereHas('latestPublishedVersion', fn (Builder $version) => $version->where('extracted_text', 'like', $needle))
                                ->orWhereHas('latestPublishedVersion.interpretation', fn (Builder $interpretation) => $interpretation
                                    ->whereRaw('CAST(locale_payloads AS TEXT) LIKE ?', [$needle]));
                        });
                });
            });
        }
    }

    private function englishScore(RegulatoryDocument $document, string $term, User $user): int
    {
        $version = $document->visibleVersionFor($user);
        $english = $version?->interpretation?->locale_payloads['en'] ?? [];
        $text = $version === null ? '' : ($version->extracted_text ?? '');
        $haystack = mb_strtolower($document->title.' '.$text.' '.json_encode($english, JSON_THROW_ON_ERROR));

        return str_contains($haystack, $term) ? 1 : 0;
    }
}
