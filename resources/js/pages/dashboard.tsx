import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bell,
    BookOpenText,
    Bot,
    CalendarDays,
    Sparkles,
} from 'lucide-react';
import { motion } from 'motion/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/lib/i18n';

type Document = {
    id: number;
    title: string;
    source: string;
    document_type: string;
    applicability: string;
    published_at: string | null;
    latest_version?: { version: number; status: string };
};

export default function Dashboard({
    stats,
    recentDocuments,
}: {
    stats: {
        documents: number;
        newThisWeek: number;
        unreadNotifications: number;
        credits: number;
    };
    recentDocuments: Document[];
}) {
    const { auth, product } = usePage().props;
    const t = useT();
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title={t('dashboard')} />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <motion.section
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="relative overflow-hidden rounded-[2rem] border border-indigo-400/20 bg-[radial-gradient(circle_at_top_right,_#5b5bd6,_#272759_58%,_#17172f)] p-7 text-white shadow-2xl shadow-indigo-950/20 md:p-10"
                >
                    <div className="absolute -top-20 -right-10 size-80 rounded-full bg-cyan-300/15 blur-3xl" />
                    <div className="relative max-w-3xl">
                        <Badge className="mb-5 border-white/15 bg-white/10 text-white hover:bg-white/10">
                            <Sparkles className="mr-1 size-3" /> Regulatory
                            intelligence, made practical
                        </Badge>
                        <h1 className="text-3xl font-semibold tracking-tight md:text-5xl">
                            {firstName}, {t('welcome').toLowerCase()}.
                        </h1>
                        <p className="mt-4 max-w-2xl text-sm leading-6 text-indigo-100/75 md:text-base">
                            Monitor RBI, Income Tax and GST updates, understand
                            their impact in your language, and explore each
                            document with grounded AI.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <Button
                                asChild
                                variant="secondary"
                                className="rounded-xl"
                            >
                                <Link href="/archive">
                                    {t('archive')}{' '}
                                    <ArrowRight className="ml-1 size-4" />
                                </Link>
                            </Button>
                            {product?.tier === 'free' && (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="rounded-xl text-white hover:bg-white/10 hover:text-white"
                                >
                                    <Link href="/billing">{t('upgrade')}</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </motion.section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        {
                            label: t('documents'),
                            value: stats.documents,
                            icon: BookOpenText,
                            tone: 'bg-indigo-500/10 text-indigo-600',
                        },
                        {
                            label: 'New this week',
                            value: stats.newThisWeek,
                            icon: CalendarDays,
                            tone: 'bg-emerald-500/10 text-emerald-600',
                        },
                        {
                            label: t('notifications'),
                            value: stats.unreadNotifications,
                            icon: Bell,
                            tone: 'bg-amber-500/10 text-amber-600',
                        },
                        {
                            label: t('credits'),
                            value:
                                product?.tier === 'tier_2' ||
                                product?.tier === 'tier_3'
                                    ? stats.credits
                                    : '—',
                            icon: Bot,
                            tone: 'bg-fuchsia-500/10 text-fuchsia-600',
                        },
                    ].map((item, index) => (
                        <motion.div
                            key={item.label}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: index * 0.05 }}
                        >
                            <Card className="rounded-2xl border-border/60 shadow-sm">
                                <CardContent className="flex items-center gap-4 p-5">
                                    <span
                                        className={`grid size-11 place-items-center rounded-xl ${item.tone}`}
                                    >
                                        <item.icon className="size-5" />
                                    </span>
                                    <div>
                                        <p className="text-2xl font-semibold tracking-tight">
                                            {item.value}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.label}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>
                    ))}
                </section>

                <Card className="rounded-3xl border-border/60 shadow-sm">
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>{t('latest')}</CardTitle>
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/archive">
                                View archive{' '}
                                <ArrowRight className="ml-1 size-4" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2">
                        {recentDocuments.length ? (
                            recentDocuments.map((document) => (
                                <Link
                                    key={document.id}
                                    href={`/archive/${document.id}`}
                                    className="group rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:border-indigo-400/50 hover:shadow-md"
                                >
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant="secondary"
                                            className="uppercase"
                                        >
                                            {document.source.replace('_', ' ')}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {document.document_type.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </span>
                                    </div>
                                    <h3 className="mt-3 line-clamp-2 leading-snug font-semibold group-hover:text-indigo-600">
                                        {document.title}
                                    </h3>
                                    <div className="mt-4 flex items-center justify-between text-xs text-muted-foreground">
                                        <span>
                                            {document.published_at
                                                ? new Date(
                                                      document.published_at,
                                                  ).toLocaleDateString()
                                                : 'Date unavailable'}
                                        </span>
                                        <span className="capitalize">
                                            {document.applicability}
                                        </span>
                                    </div>
                                </Link>
                            ))
                        ) : (
                            <div className="col-span-full rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                                No regulatory documents have been ingested yet.
                                The scheduled source adapters will populate this
                                archive.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
