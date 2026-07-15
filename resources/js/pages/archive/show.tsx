import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Bot,
    CalendarDays,
    Download,
    ExternalLink,
    FileDown,
    Flag,
    Languages,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';

type Payload = {
    summary: string;
    takeaways: string[];
    glossary: { term: string; definition: string }[];
    deadlines: { due_date: string; description: string }[];
    applicability_tags: string[];
    effective_date: string | null;
    document_type: string;
};
type Version = {
    id: number;
    version: number;
    status: string;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    interpretation: Payload | null;
    interpretation_locale: string | null;
    requested_locale: string;
    locale_fallback: boolean;
    interpretation_meta: {
        id: number;
        status: string;
        model_id: string;
        prompt_version: string;
        generated_at: string;
    } | null;
};
type Document = {
    id: number;
    title: string;
    source: string;
    document_type: string;
    applicability: string;
    published_at: string | null;
    effective_at: string | null;
    source_url: string | null;
    latest_version: Version | null;
    versions: {
        id: number;
        version: number;
        status: string;
        acquired_at: string;
    }[];
};

export default function ArchiveShow({
    document,
    capabilities,
}: {
    document: Document;
    capabilities: { interpretations: boolean; exports: boolean; chat: boolean };
}) {
    const t = useT();
    const v = document.latest_version;
    const report = useForm({
        category: '',
        locale: v?.requested_locale ?? 'en',
        description: '',
    });
    const submitReport = (e: FormEvent) => {
        e.preventDefault();

        if (v?.interpretation_meta) {
            report.post(`/interpretations/${v.interpretation_meta.id}/issues`, {
                onSuccess: () => report.reset('description'),
            });
        }
    };

    return (
        <>
            <Head title={document.title} />
            <div className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <Link
                    href="/archive"
                    className="mb-5 inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="mr-1 size-4" /> Back to archive
                </Link>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_290px]">
                    <main className="space-y-6">
                        <section className="rounded-3xl border bg-gradient-to-br from-indigo-950 to-slate-950 p-7 text-white shadow-xl">
                            <div className="flex flex-wrap gap-2">
                                <Badge className="bg-white/10 text-white uppercase hover:bg-white/10">
                                    {document.source.replace('_', ' ')}
                                </Badge>
                                <Badge className="bg-white/10 text-white capitalize hover:bg-white/10">
                                    {document.document_type.replaceAll(
                                        '_',
                                        ' ',
                                    )}
                                </Badge>
                                <Badge className="bg-white/10 text-white capitalize hover:bg-white/10">
                                    {document.applicability}
                                </Badge>
                            </div>
                            <h1 className="mt-5 text-2xl leading-tight font-semibold md:text-4xl">
                                {document.title}
                            </h1>
                            <div className="mt-5 flex flex-wrap gap-5 text-sm text-slate-300">
                                <span className="flex items-center gap-1.5">
                                    <CalendarDays className="size-4" />{' '}
                                    Published{' '}
                                    {document.published_at
                                        ? new Date(
                                              document.published_at,
                                          ).toLocaleDateString()
                                        : 'date unavailable'}
                                </span>
                                {document.effective_at && (
                                    <span>
                                        Effective{' '}
                                        {new Date(
                                            document.effective_at,
                                        ).toLocaleDateString()}
                                    </span>
                                )}
                            </div>
                        </section>
                        {capabilities.interpretations && v?.interpretation ? (
                            <>
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-card p-3">
                                    <span className="text-sm font-medium">
                                        Interpretation language
                                    </span>
                                    <div className="flex gap-1">
                                        {['en', 'hi', 'gu', 'mr'].map(
                                            (locale) => (
                                                <Button
                                                    key={locale}
                                                    asChild
                                                    size="sm"
                                                    variant={
                                                        v.requested_locale ===
                                                        locale
                                                            ? 'default'
                                                            : 'ghost'
                                                    }
                                                >
                                                    <Link
                                                        href={`/archive/${document.id}?locale=${locale}&version=${v.id}`}
                                                        preserveScroll
                                                    >
                                                        {locale.toUpperCase()}
                                                    </Link>
                                                </Button>
                                            ),
                                        )}
                                    </div>
                                </div>
                                {v.locale_fallback && (
                                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/20 dark:text-amber-100">
                                        This interpretation is not available in{' '}
                                        {v.requested_locale}. Showing the
                                        English version.
                                    </div>
                                )}
                                {v.interpretation.applicability_tags?.length >
                                    0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {v.interpretation.applicability_tags.map(
                                            (tag) => (
                                                <Badge
                                                    key={tag}
                                                    variant="secondary"
                                                    className="capitalize"
                                                >
                                                    {tag}
                                                </Badge>
                                            ),
                                        )}
                                    </div>
                                )}
                                <Card className="rounded-2xl">
                                    <CardHeader>
                                        <div className="flex items-center justify-between gap-4">
                                            <CardTitle className="flex items-center gap-2">
                                                <Languages className="size-5 text-indigo-600" />{' '}
                                                {t('summary')}
                                            </CardTitle>
                                            <Badge
                                                variant="outline"
                                                className="uppercase"
                                            >
                                                {v.interpretation_locale}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-[15px] leading-7 whitespace-pre-line text-foreground/85">
                                            {v.interpretation.summary}
                                        </p>
                                    </CardContent>
                                </Card>
                                {v.interpretation_meta && (
                                    <p className="px-1 text-xs text-muted-foreground">
                                        Generated by{' '}
                                        {v.interpretation_meta.model_id} ·
                                        prompt{' '}
                                        {v.interpretation_meta.prompt_version} ·{' '}
                                        {new Date(
                                            v.interpretation_meta.generated_at,
                                        ).toLocaleString()}
                                    </p>
                                )}
                                <Card className="rounded-2xl">
                                    <CardHeader>
                                        <CardTitle>{t('takeaways')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ol className="space-y-3">
                                            {v.interpretation.takeaways.map(
                                                (item, i) => (
                                                    <li
                                                        key={i}
                                                        className="flex gap-3"
                                                    >
                                                        <span className="grid size-6 shrink-0 place-items-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-950">
                                                            {i + 1}
                                                        </span>
                                                        <span className="text-sm leading-6">
                                                            {item}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ol>
                                    </CardContent>
                                </Card>
                                {v.interpretation.deadlines?.length > 0 && (
                                    <Card className="rounded-2xl border-amber-300/60 bg-amber-50/40 dark:bg-amber-950/10">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <AlertTriangle className="size-5 text-amber-600" />{' '}
                                                Deadlines
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {v.interpretation.deadlines.map(
                                                (d, i) => (
                                                    <div key={i}>
                                                        <p className="font-semibold">
                                                            {d.due_date}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {d.description}
                                                        </p>
                                                    </div>
                                                ),
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                                {v.interpretation.glossary?.length > 0 && (
                                    <Card className="rounded-2xl">
                                        <CardHeader>
                                            <CardTitle>
                                                {t('glossary')}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="grid gap-4 sm:grid-cols-2">
                                            {v.interpretation.glossary.map(
                                                (item, i) => (
                                                    <div
                                                        key={i}
                                                        className="rounded-xl bg-muted/60 p-4"
                                                    >
                                                        <dt className="font-semibold">
                                                            {item.term}
                                                        </dt>
                                                        <dd className="mt-1 text-sm leading-6 text-muted-foreground">
                                                            {item.definition}
                                                        </dd>
                                                    </div>
                                                ),
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                            </>
                        ) : capabilities.interpretations ? (
                            <Card className="rounded-2xl border-dashed">
                                <CardContent className="p-8 text-center text-muted-foreground">
                                    {t('unavailable')}
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="rounded-2xl border-indigo-200 bg-indigo-50/50 dark:bg-indigo-950/20">
                                <CardContent className="flex items-center justify-between gap-5 p-6">
                                    <div>
                                        <p className="font-semibold">
                                            Unlock plain-language
                                            interpretations
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Tier 1 adds four-language
                                            explanations, exports and alerts.
                                        </p>
                                    </div>
                                    <Button asChild>
                                        <Link href="/billing">
                                            {t('upgrade')}
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </main>
                    <aside className="space-y-4">
                        <Card className="rounded-2xl">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Original publication
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button asChild className="w-full">
                                    <a
                                        href={`/archive/${document.id}/download?version=${v?.id}`}
                                    >
                                        <Download className="mr-1 size-4" />{' '}
                                        {t('download')}
                                    </a>
                                </Button>
                                {document.source_url && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <a
                                            href={document.source_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink className="mr-1 size-4" />{' '}
                                            Source website
                                        </a>
                                    </Button>
                                )}
                                <div className="pt-2 text-xs leading-5 text-muted-foreground">
                                    Version {v?.version} · {v?.mime_type}
                                    <br />
                                    Original text is never translated or
                                    altered.
                                </div>
                            </CardContent>
                        </Card>
                        {document.versions.length > 1 && (
                            <Card className="rounded-2xl">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Versions
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {document.versions.map((version) => (
                                        <Link
                                            key={version.id}
                                            href={`/archive/${document.id}?version=${version.id}&locale=${v?.requested_locale ?? 'en'}`}
                                            className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-sm transition hover:bg-muted"
                                        >
                                            <span>
                                                Version {version.version}
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {new Date(
                                                        version.acquired_at,
                                                    ).toLocaleDateString()}
                                                </span>
                                            </span>
                                            <Badge variant="outline">
                                                {version.status}
                                            </Badge>
                                        </Link>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                        {capabilities.chat && (
                            <Card className="rounded-2xl border-fuchsia-200 bg-fuchsia-50/40 dark:bg-fuchsia-950/10">
                                <CardContent className="p-5">
                                    <Bot className="mb-3 size-7 text-fuchsia-600" />
                                    <p className="font-semibold">
                                        Explore with grounded AI
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Every answer stays scoped to this exact
                                        version.
                                    </p>
                                    <Button
                                        className="mt-4 w-full"
                                        onClick={() =>
                                            router.post(
                                                `/documents/${document.id}/chats`,
                                                { version: v?.id },
                                            )
                                        }
                                    >
                                        <Bot className="mr-1 size-4" />{' '}
                                        {t('startChat')}
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                        {capabilities.exports && v?.interpretation_meta && (
                            <Card className="rounded-2xl">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Export interpretation
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-2">
                                    <Button variant="outline" asChild>
                                        <a
                                            href={`/interpretations/${v.interpretation_meta.id}/export/md`}
                                        >
                                            <FileDown className="mr-1 size-4" />{' '}
                                            MD
                                        </a>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a
                                            href={`/interpretations/${v.interpretation_meta.id}/export/pdf`}
                                        >
                                            <FileDown className="mr-1 size-4" />{' '}
                                            PDF
                                        </a>
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                        {capabilities.interpretations &&
                            v?.interpretation_meta && (
                                <Card className="rounded-2xl">
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Flag className="size-4" /> Report
                                            an issue
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <form
                                            onSubmit={submitReport}
                                            className="space-y-3"
                                        >
                                            <select
                                                value={report.data.category}
                                                onChange={(e) =>
                                                    report.setData(
                                                        'category',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                            >
                                                <option value="">
                                                    No category
                                                </option>
                                                <option value="inaccurate">
                                                    Inaccurate
                                                </option>
                                                <option value="mistranslation">
                                                    Mistranslation
                                                </option>
                                                <option value="missing_takeaway">
                                                    Missing takeaway
                                                </option>
                                                <option value="wrong_applicability">
                                                    Wrong applicability
                                                </option>
                                                <option value="other">
                                                    Other
                                                </option>
                                            </select>
                                            <Textarea
                                                value={report.data.description}
                                                onChange={(e) =>
                                                    report.setData(
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="What should our reviewers check?"
                                                required
                                            />
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                disabled={report.processing}
                                            >
                                                Send report
                                            </Button>
                                        </form>
                                    </CardContent>
                                </Card>
                            )}
                    </aside>
                </div>
            </div>
        </>
    );
}

ArchiveShow.layout = {
    breadcrumbs: [
        { title: 'Archive', href: '/archive' },
        { title: 'Document', href: '#' },
    ],
};
