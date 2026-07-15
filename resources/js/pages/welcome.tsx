import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Blocks,
    Radio,
    ShieldCheck,
    Sparkles,
    Workflow,
} from 'lucide-react';
import { motion } from 'motion/react';
import { Button } from '@/components/ui/button';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Build the product, not the plumbing" />
            <main className="min-h-screen overflow-hidden bg-[#f8f8fb] text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
                <nav className="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-2 font-semibold"
                    >
                        <span className="grid size-9 place-items-center rounded-xl bg-zinc-950 text-white dark:bg-white dark:text-zinc-950">
                            <Sparkles className="size-4" />
                        </span>
                        SahkarAI
                    </Link>
                    <div className="flex items-center gap-2">
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
                                        Start building{' '}
                                        <ArrowRight className="ml-1 size-4" />
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </nav>

                <section className="relative mx-auto max-w-7xl px-6 pt-20 pb-24 text-center lg:px-8 lg:pt-28">
                    <div className="pointer-events-none absolute top-8 left-1/2 -z-0 h-96 w-2/3 -translate-x-1/2 rounded-full bg-violet-400/20 blur-[100px]" />
                    <motion.div
                        initial={{ opacity: 0, y: 14 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="relative"
                    >
                        <p className="mx-auto mb-6 w-fit rounded-full border bg-white/70 px-4 py-2 text-xs font-medium shadow-sm backdrop-blur dark:bg-zinc-900/70">
                            <span className="mr-2 inline-block size-2 rounded-full bg-emerald-500" />
                            Laravel 13 · React 19 · Production ready
                        </p>
                        <h1 className="mx-auto max-w-4xl text-5xl font-semibold tracking-[-0.045em] text-balance sm:text-7xl">
                            Start where your product becomes{' '}
                            <span className="text-violet-600">
                                interesting.
                            </span>
                        </h1>
                        <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-pretty text-zinc-600 dark:text-zinc-400">
                            Authentication, organizations, permissions, queues,
                            realtime, AI primitives and deployment are already
                            decided. Give your agents the business problem and
                            start where the product becomes interesting.
                        </p>
                        <div className="mt-9 flex justify-center gap-3">
                            <Button
                                asChild
                                size="lg"
                                className="h-12 rounded-xl px-6"
                            >
                                <Link href="/register">
                                    Create your workspace{' '}
                                    <ArrowRight className="ml-2 size-4" />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-12 rounded-xl bg-white/70 px-6 dark:bg-zinc-900/70"
                            >
                                <a href="/up">Check runtime</a>
                            </Button>
                        </div>
                    </motion.div>

                    <motion.div
                        initial={{ opacity: 0, scale: 0.98 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ delay: 0.15 }}
                        className="relative mx-auto mt-20 grid max-w-5xl gap-px overflow-hidden rounded-3xl border bg-zinc-200 p-px text-left shadow-2xl shadow-violet-950/10 md:grid-cols-2 dark:bg-zinc-800"
                    >
                        {[
                            {
                                icon: ShieldCheck,
                                title: 'Tenant-safe by structure',
                                copy: 'Explicit context, policies, scoped models and isolation tests—not controller conventions.',
                            },
                            {
                                icon: Workflow,
                                title: 'One product grammar',
                                copy: 'Request → policy → action → event → queued side effect. Agents always know where code belongs.',
                            },
                            {
                                icon: Radio,
                                title: 'Realtime and streaming',
                                copy: 'SSE and Reverb WebSockets are proven through the same production proxy and TLS path.',
                            },
                            {
                                icon: Blocks,
                                title: 'A real reference module',
                                copy: 'Projects demonstrates the complete backend, frontend, audit and test pattern end to end.',
                            },
                        ].map((feature) => (
                            <article
                                key={feature.title}
                                className="bg-white p-8 dark:bg-zinc-900"
                            >
                                <span className="grid size-10 place-items-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                    <feature.icon className="size-5" />
                                </span>
                                <h2 className="mt-5 font-semibold">
                                    {feature.title}
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                    {feature.copy}
                                </p>
                            </article>
                        ))}
                    </motion.div>
                </section>
            </main>
        </>
    );
}
