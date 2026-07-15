import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    Bot,
    BookOpenText,
    Languages,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { motion } from 'motion/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Regulatory intelligence for co-operative finance" />
            <main className="min-h-screen overflow-hidden bg-[#f7f7fb] text-slate-950 dark:bg-slate-950 dark:text-slate-50">
                <nav className="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-2 font-semibold"
                    >
                        <span className="grid size-9 place-items-center rounded-xl bg-indigo-600 text-white">
                            <Sparkles className="size-4" />
                        </span>
                        SahkarAI
                    </Link>
                    <div className="flex gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href="/dashboard">Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href="/login">Sign in</Link>
                                </Button>
                                <Button asChild className="rounded-xl">
                                    <Link href="/register">
                                        Create free account{' '}
                                        <ArrowRight className="ml-1 size-4" />
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </nav>
                <section className="relative mx-auto max-w-7xl px-6 pt-20 pb-24 text-center lg:px-8 lg:pt-28">
                    <div className="pointer-events-none absolute top-0 left-1/2 h-[28rem] w-2/3 -translate-x-1/2 rounded-full bg-indigo-400/20 blur-[110px]" />
                    <motion.div
                        initial={{ opacity: 0, y: 14 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="relative"
                    >
                        <Badge
                            variant="outline"
                            className="mb-6 bg-white/60 px-4 py-2 dark:bg-slate-900/60"
                        >
                            <span className="mr-2 size-2 rounded-full bg-emerald-500" />{' '}
                            RBI · Income Tax · GST
                        </Badge>
                        <h1 className="mx-auto max-w-5xl text-5xl font-semibold tracking-[-.05em] text-balance sm:text-7xl">
                            Regulatory updates,
                            <br />
                            <span className="bg-gradient-to-r from-indigo-600 to-fuchsia-600 bg-clip-text text-transparent">
                                understood and actionable.
                            </span>
                        </h1>
                        <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-400">
                            A living regulatory archive for India’s co-operative
                            financial sector—with plain-language
                            interpretations, multilingual alerts and AI
                            conversations grounded in the original publication.
                        </p>
                        <div className="mt-9 flex justify-center gap-3">
                            <Button
                                asChild
                                size="lg"
                                className="h-12 rounded-xl px-6"
                            >
                                <Link
                                    href={auth.user ? '/archive' : '/register'}
                                >
                                    Explore SahkarAI{' '}
                                    <ArrowRight className="ml-2 size-4" />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-12 rounded-xl bg-white/60 px-6 dark:bg-slate-900/60"
                            >
                                <Link href="/login">Member sign in</Link>
                            </Button>
                        </div>
                    </motion.div>
                    <motion.div
                        initial={{ opacity: 0, scale: 0.98 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ delay: 0.15 }}
                        className="relative mx-auto mt-20 grid max-w-5xl gap-px overflow-hidden rounded-3xl border bg-slate-200 p-px text-left shadow-2xl shadow-indigo-950/10 md:grid-cols-2 dark:bg-slate-800"
                    >
                        {[
                            {
                                icon: BookOpenText,
                                title: 'One authoritative archive',
                                copy: 'Browse current and historical publications with the original file, revision chain and exact metadata intact.',
                            },
                            {
                                icon: Languages,
                                title: 'Clarity in four languages',
                                copy: 'Read carefully structured interpretations in English, Hindi, Gujarati or Marathi, with explicit fallback.',
                            },
                            {
                                icon: BellRing,
                                title: 'Updates matched to you',
                                copy: 'Choose the sources and cadence that matter. Backfills and failed interpretations never create noise.',
                            },
                            {
                                icon: Bot,
                                title: 'AI grounded in one document',
                                copy: 'Ask follow-up questions without corpus drift. Every private chat stays bound to an immutable document version.',
                            },
                        ].map((feature) => (
                            <article
                                key={feature.title}
                                className="bg-white p-8 dark:bg-slate-900"
                            >
                                <span className="grid size-10 place-items-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    <feature.icon className="size-5" />
                                </span>
                                <h2 className="mt-5 font-semibold">
                                    {feature.title}
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">
                                    {feature.copy}
                                </p>
                            </article>
                        ))}
                    </motion.div>
                    <div className="mx-auto mt-10 flex max-w-2xl items-center justify-center gap-2 text-xs text-slate-500">
                        <ShieldCheck className="size-4" /> Educational
                        regulatory information, never a substitute for legal or
                        professional advice.
                    </div>
                </section>
            </main>
        </>
    );
}
