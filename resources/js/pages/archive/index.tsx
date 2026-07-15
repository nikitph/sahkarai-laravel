import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Calendar,
    FileText,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useT } from '@/lib/i18n';

type Document = {
    id: number;
    title: string;
    source: string;
    document_type: string;
    applicability: string;
    applicability_tags: string[];
    published_at: string | null;
    effective_at: string | null;
    version: number | null;
    status: string | null;
    snippet: string;
};
type Page = {
    data: Document[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
};
type Option = { value: string; name?: string };

export default function ArchiveIndex({
    documents,
    filters,
    filterOptions,
}: {
    documents: Page;
    filters: Record<string, string>;
    filterOptions: {
        sources: Option[];
        types: Option[];
        applicability: Option[];
    };
}) {
    const t = useT();
    const form = useForm({
        q: filters.q ?? '',
        source: filters.source ?? '',
        document_type: filters.document_type ?? '',
        applicability: filters.applicability ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        sort: filters.sort ?? 'newest',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.get('/archive', { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={t('archive')} />
            <div className="mx-auto w-full max-w-7xl p-4 md:p-8">
                <div className="mb-7">
                    <Badge variant="secondary" className="mb-3">
                        <FileText className="mr-1 size-3" /> Live regulatory
                        corpus
                    </Badge>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {t('archive')}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Search current and historical RBI, Income Tax and GST
                        publications. Originals remain the source of truth.
                    </p>
                </div>
                <Card className="mb-6 rounded-2xl border-border/60">
                    <CardContent className="p-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-3 lg:grid-cols-4"
                        >
                            <div className="relative">
                                <Search className="absolute top-3 left-3 size-4 text-muted-foreground" />
                                <Input
                                    value={form.data.q}
                                    onChange={(e) =>
                                        form.setData('q', e.target.value)
                                    }
                                    placeholder="Keyword or quoted phrase"
                                    className="pl-9"
                                />
                            </div>
                            {[
                                [
                                    'source',
                                    'All sources',
                                    filterOptions.sources,
                                ],
                                [
                                    'document_type',
                                    'All document types',
                                    filterOptions.types,
                                ],
                                [
                                    'applicability',
                                    'All applicability',
                                    filterOptions.applicability,
                                ],
                            ].map(([key, label, options]) => (
                                <select
                                    key={key as string}
                                    value={form.data[key as 'source']}
                                    onChange={(e) =>
                                        form.setData(
                                            key as 'source',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">{label as string}</option>
                                    {(options as Option[]).map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.value.replaceAll('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                            ))}
                            <Input
                                type="date"
                                aria-label="Published from"
                                value={form.data.date_from}
                                onChange={(e) =>
                                    form.setData('date_from', e.target.value)
                                }
                            />
                            <Input
                                type="date"
                                aria-label="Published to"
                                value={form.data.date_to}
                                onChange={(e) =>
                                    form.setData('date_to', e.target.value)
                                }
                            />
                            <select
                                value={form.data.sort}
                                onChange={(e) =>
                                    form.setData('sort', e.target.value)
                                }
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="newest">Newest first</option>
                                <option value="title">Title A–Z</option>
                            </select>
                            <Button type="submit" className="lg:col-span-4">
                                <SlidersHorizontal className="mr-1 size-4" />{' '}
                                Apply
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <div className="mb-3 flex items-center justify-between text-sm text-muted-foreground">
                    <span>{documents.total} documents</span>
                    {documents.from && (
                        <span>
                            {documents.from}–{documents.to}
                        </span>
                    )}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {documents.data.map((document) => (
                        <Link
                            href={`/archive/${document.id}`}
                            key={document.id}
                            className="group"
                        >
                            <Card className="h-full rounded-2xl border-border/60 transition hover:-translate-y-0.5 hover:border-indigo-400/60 hover:shadow-lg">
                                <CardContent className="p-5">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="flex gap-2">
                                            <Badge
                                                className="uppercase"
                                                variant="secondary"
                                            >
                                                {document.source.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                            {(
                                                document.applicability_tags ?? [
                                                    document.applicability,
                                                ]
                                            ).map((tag) => (
                                                <Badge
                                                    key={tag}
                                                    variant="outline"
                                                    className="capitalize"
                                                >
                                                    {tag}
                                                </Badge>
                                            ))}
                                        </div>
                                        <span className="text-xs text-muted-foreground">
                                            v{document.version}
                                        </span>
                                    </div>
                                    <h2 className="mt-4 line-clamp-2 text-lg leading-snug font-semibold group-hover:text-indigo-600">
                                        {document.title}
                                    </h2>
                                    <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">
                                        <Highlighted
                                            text={
                                                document.snippet ||
                                                'Open the original publication and metadata.'
                                            }
                                            query={form.data.q}
                                        />
                                    </p>
                                    <div className="mt-5 flex items-center justify-between border-t pt-4 text-xs text-muted-foreground">
                                        <span className="flex items-center gap-1">
                                            <Calendar className="size-3.5" />{' '}
                                            {document.published_at
                                                ? new Date(
                                                      document.published_at,
                                                  ).toLocaleDateString()
                                                : 'No date'}
                                        </span>
                                        <span className="flex items-center font-medium text-indigo-600">
                                            {t('view')}{' '}
                                            <ArrowRight className="ml-1 size-3.5" />
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
                {!documents.data.length && (
                    <div className="rounded-2xl border border-dashed p-12 text-center">
                        <Search className="mx-auto mb-3 size-8 text-muted-foreground" />
                        <p className="font-medium">
                            No documents match these filters.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Try a broader query or clear one of the filters.
                        </p>
                    </div>
                )}
                <div className="mt-7 flex flex-wrap justify-center gap-1">
                    {documents.links.map((link, i) => (
                        <Button
                            key={i}
                            asChild={!!link.url}
                            disabled={!link.url}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                        >
                            {link.url ? (
                                <Link
                                    href={link.url}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            )}
                        </Button>
                    ))}
                </div>
            </div>
        </>
    );
}

ArchiveIndex.layout = { breadcrumbs: [{ title: 'Archive', href: '/archive' }] };

function Highlighted({ text, query }: { text: string; query: string }) {
    const term = query.trim().replace(/^"|"$/g, '');

    if (!term) {
        return text;
    }

    const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const parts = text.split(new RegExp(`(${escaped})`, 'ig'));

    return (
        <>
            {parts.map((part, index) =>
                part.toLowerCase() === term.toLowerCase() ? (
                    <mark
                        key={index}
                        className="rounded bg-amber-200 px-0.5 dark:bg-amber-800"
                    >
                        {part}
                    </mark>
                ) : (
                    part
                ),
            )}
        </>
    );
}
