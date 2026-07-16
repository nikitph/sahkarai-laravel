import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    Bot,
    Check,
    FileCheck2,
    Languages,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { motion } from 'motion/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

const features = [
    {
        number: '01',
        icon: Search,
        title: 'Archive & search',
        copy: 'Find RBI, Income Tax and GST publications by source, date, type and applicability—with the original document always one click away.',
    },
    {
        number: '02',
        icon: Languages,
        title: 'Interpretations in four languages',
        copy: 'Move from regulatory language to a structured explanation in English, Hindi, Gujarati or Marathi, with fallback made explicit.',
    },
    {
        number: '03',
        icon: Bot,
        title: 'Ask one document at a time',
        copy: 'Open a private conversation tied to an immutable document version, so follow-up answers stay inside the source you selected.',
    },
    {
        number: '04',
        icon: BellRing,
        title: 'Notifications without the noise',
        copy: 'Choose the sources and cadence that matter to you. Failed or incomplete interpretations never masquerade as finished guidance.',
    },
];

const plans = [
    {
        name: 'Free',
        price: '₹0',
        description: 'Build your regulatory reading habit.',
        features: [
            'Browse the archive',
            'Read original publications',
            'Search and filter documents',
        ],
    },
    {
        name: 'Tier 1',
        price: '₹999',
        description: 'Turn dense updates into clear next steps.',
        features: [
            'Everything in Free',
            'Four-language interpretations',
            'Exports and smart notifications',
        ],
    },
    {
        name: 'Tier 2',
        price: '₹1,499',
        description: 'Investigate each regulation in depth.',
        features: [
            'Everything in Tier 1',
            'Document-grounded AI chat',
            '200 chat credits each month',
        ],
        featured: true,
    },
    {
        name: 'Tier 3',
        price: '₹2,499',
        description: 'Make the AI workspace adapt to how you work.',
        features: [
            'Everything in Tier 2',
            'Personalized LLM chat configuration',
            'Custom response style and detail',
        ],
    },
];

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="mb-7 flex items-center gap-4 text-[11px] font-semibold tracking-[0.22em] text-slate-500 uppercase dark:text-slate-400">
            <span className="h-px w-8 bg-current opacity-40" />
            {children}
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;
    const primaryHref = auth.user ? '/archive' : '/register';

    return (
        <>
            <Head title="Regulatory intelligence for Indian co-operatives" />
            <main className="min-h-screen overflow-hidden bg-[#f7f7fb] text-[#17171d] selection:bg-sky-200 dark:bg-[#0b0b11] dark:text-slate-50 dark:selection:bg-teal-900">
                <header className="sticky top-0 z-50 border-b border-black/5 bg-[#f7f7fb]/85 backdrop-blur-xl dark:border-white/10 dark:bg-[#0b0b11]/85">
                    <nav className="mx-auto flex h-20 max-w-[1400px] items-center justify-between px-6 lg:px-12">
                        <Link
                            href="/"
                            className="flex items-center gap-2.5 font-semibold tracking-tight"
                        >
                            <AppLogoIcon className="h-11 w-16" />
                            <span className="text-lg">SahkarAI</span>
                        </Link>
                        <div className="hidden items-center gap-9 text-sm text-slate-600 md:flex dark:text-slate-300">
                            <a
                                href="#features"
                                className="transition-colors hover:text-teal-600"
                            >
                                Features
                            </a>
                            <a
                                href="#workflow"
                                className="transition-colors hover:text-teal-600"
                            >
                                How it works
                            </a>
                            <a
                                href="#pricing"
                                className="transition-colors hover:text-teal-600"
                            >
                                Pricing
                            </a>
                        </div>
                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Button asChild className="rounded-full px-5">
                                    <Link href="/dashboard">Dashboard</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        className="hidden rounded-full sm:inline-flex"
                                    >
                                        <Link href="/login">Sign in</Link>
                                    </Button>
                                    <Button
                                        asChild
                                        className="rounded-full px-5"
                                    >
                                        <Link href="/register">
                                            Start free{' '}
                                            <ArrowRight className="ml-1 size-4" />
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <section className="relative border-b border-black/5 dark:border-white/10">
                    <div
                        className="pointer-events-none absolute inset-0 opacity-45 dark:opacity-20"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, rgba(13,148,136,.09) 1px, transparent 1px), linear-gradient(to bottom, rgba(13,148,136,.09) 1px, transparent 1px)',
                            backgroundSize: '72px 72px',
                            maskImage:
                                'linear-gradient(to bottom, black, transparent 88%)',
                        }}
                    />
                    <div className="pointer-events-none absolute -top-48 right-[-12rem] size-[44rem] rounded-full bg-emerald-400/15 blur-[130px]" />
                    <div className="relative mx-auto grid min-h-[calc(100vh-5rem)] max-w-[1400px] items-center gap-16 px-6 py-20 lg:grid-cols-[1.12fr_.88fr] lg:px-12 lg:py-28">
                        <motion.div
                            initial={{ opacity: 0, y: 24 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.7 }}
                        >
                            <SectionLabel>
                                Regulatory intelligence for Indian co-operatives
                            </SectionLabel>
                            <h1 className="max-w-4xl text-[clamp(4rem,9vw,8.7rem)] leading-[0.83] font-semibold tracking-[-0.07em] text-balance">
                                From circular
                                <span className="block bg-gradient-to-r from-sky-600 via-teal-600 to-emerald-600 bg-clip-text pb-3 text-transparent">
                                    to clarity.
                                </span>
                            </h1>
                            <div className="mt-10 grid max-w-3xl gap-8 border-t border-black/10 pt-8 sm:grid-cols-[1fr_auto] sm:items-end dark:border-white/10">
                                <p className="max-w-xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                                    Follow RBI, Income Tax and GST updates. Read
                                    the original, understand the change in your
                                    language, and ask questions grounded in the
                                    exact publication.
                                </p>
                                <div className="flex flex-col gap-3 sm:items-end">
                                    <Button
                                        asChild
                                        size="lg"
                                        className="h-13 rounded-full px-7 shadow-lg shadow-teal-900/15"
                                    >
                                        <Link href={primaryHref}>
                                            {auth.user
                                                ? 'Open the archive'
                                                : 'Start exploring free'}{' '}
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                    {!auth.user && (
                                        <span className="text-xs text-slate-500">
                                            No credit card required
                                        </span>
                                    )}
                                </div>
                            </div>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, x: 24, rotate: 1 }}
                            animate={{ opacity: 1, x: 0, rotate: 0 }}
                            transition={{ delay: 0.15, duration: 0.75 }}
                            className="relative mx-auto w-full max-w-xl"
                        >
                            <div className="absolute inset-8 rounded-[2.5rem] bg-teal-500/20 blur-3xl" />
                            <div className="relative overflow-hidden rounded-[2rem] border border-white/60 bg-white/85 p-3 shadow-[0_35px_100px_-40px_rgba(15,118,110,.4)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/85">
                                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                                    <div>
                                        <p className="text-[10px] font-semibold tracking-[0.2em] text-teal-600 uppercase">
                                            Latest publication
                                        </p>
                                        <p className="mt-1 text-sm font-semibold">
                                            RBI regulatory update
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                        Source preserved
                                    </span>
                                </div>
                                <div className="grid gap-3 p-3 sm:grid-cols-[.82fr_1.18fr]">
                                    <div className="flex min-h-64 flex-col rounded-2xl bg-slate-950 p-5 text-white">
                                        <FileCheck2 className="size-7 text-sky-300" />
                                        <div className="mt-auto">
                                            <p className="text-xs text-slate-400">
                                                Original document
                                            </p>
                                            <p className="mt-2 font-medium">
                                                Versioned, searchable and ready
                                                to download.
                                            </p>
                                            <div className="mt-5 flex gap-1.5">
                                                {[72, 54, 65, 42].map(
                                                    (width) => (
                                                        <span
                                                            key={width}
                                                            className="h-1 rounded-full bg-white/20"
                                                            style={{ width }}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="rounded-2xl bg-teal-50 p-5 dark:bg-teal-950/50">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-semibold text-teal-700 dark:text-teal-300">
                                                Plain-language interpretation
                                            </span>
                                            <span className="text-[10px] text-slate-500">
                                                EN · HI · GU · MR
                                            </span>
                                        </div>
                                        <h2 className="mt-6 text-xl font-semibold tracking-tight">
                                            What changed—and what to review
                                            next.
                                        </h2>
                                        <div className="mt-5 space-y-3">
                                            {[
                                                'Scope and applicability',
                                                'Key dates and obligations',
                                                'Questions to take to your adviser',
                                            ].map((item, index) => (
                                                <div
                                                    key={item}
                                                    className="flex items-start gap-3 rounded-xl bg-white/70 p-3 text-xs dark:bg-slate-900/60"
                                                >
                                                    <span className="grid size-5 shrink-0 place-items-center rounded-full bg-teal-600 text-[10px] text-white">
                                                        {index + 1}
                                                    </span>
                                                    <span className="pt-0.5 text-slate-700 dark:text-slate-200">
                                                        {item}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </motion.div>
                    </div>
                </section>

                <section className="border-b border-black/5 dark:border-white/10">
                    <div className="mx-auto grid max-w-[1400px] grid-cols-3 px-6 lg:px-12">
                        {[
                            'Reserve Bank of India',
                            'Income Tax',
                            'Goods & Services Tax',
                        ].map((source, index) => (
                            <div
                                key={source}
                                className="border-x border-black/5 px-3 py-7 text-center dark:border-white/10"
                            >
                                <span className="mr-2 text-xs text-slate-400">
                                    0{index + 1}
                                </span>
                                <span className="text-xs font-semibold tracking-wide sm:text-sm">
                                    {source}
                                </span>
                            </div>
                        ))}
                    </div>
                </section>

                <section
                    id="features"
                    className="mx-auto max-w-[1400px] px-6 py-28 lg:px-12 lg:py-40"
                >
                    <motion.div
                        initial={{ opacity: 0, y: 18 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true, amount: 0.3 }}
                    >
                        <SectionLabel>
                            Built for the work after the update lands
                        </SectionLabel>
                        <h2 className="max-w-5xl text-5xl leading-[0.98] font-semibold tracking-[-0.05em] text-balance md:text-7xl">
                            Less time decoding.
                            <br />
                            <span className="text-slate-400 dark:text-slate-600">
                                More confidence deciding.
                            </span>
                        </h2>
                    </motion.div>
                    <div className="mt-20 grid gap-px overflow-hidden rounded-3xl border border-black/10 bg-black/10 md:grid-cols-2 dark:border-white/10 dark:bg-white/10">
                        {features.map((feature) => (
                            <article
                                key={feature.title}
                                className="group bg-[#f7f7fb] p-8 transition-colors hover:bg-white md:p-12 dark:bg-[#0b0b11] dark:hover:bg-slate-900"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-mono text-xs text-slate-400">
                                        {feature.number}
                                    </span>
                                    <span className="grid size-12 place-items-center rounded-full border border-black/10 text-teal-600 transition-transform group-hover:scale-110 group-hover:-rotate-6 dark:border-white/10 dark:text-teal-300">
                                        <feature.icon className="size-5" />
                                    </span>
                                </div>
                                <h3 className="mt-16 text-3xl font-semibold tracking-[-0.035em]">
                                    {feature.title}
                                </h3>
                                <p className="mt-4 max-w-lg leading-7 text-slate-600 dark:text-slate-400">
                                    {feature.copy}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>

                <section
                    id="workflow"
                    className="relative overflow-hidden bg-slate-950 py-28 text-white lg:py-40"
                >
                    <div
                        className="pointer-events-none absolute inset-0 opacity-10"
                        style={{
                            backgroundImage:
                                'repeating-linear-gradient(-45deg, transparent, transparent 44px, white 45px)',
                        }}
                    />
                    <div className="relative mx-auto max-w-[1400px] px-6 lg:px-12">
                        <SectionLabel>How it works</SectionLabel>
                        <div className="grid gap-16 lg:grid-cols-[.8fr_1.2fr] lg:gap-24">
                            <h2 className="text-5xl leading-[.95] font-semibold tracking-[-0.055em] md:text-7xl">
                                Three steps.
                                <br />
                                <span className="text-white/35">
                                    One source of truth.
                                </span>
                            </h2>
                            <div className="border-t border-white/15">
                                {[
                                    [
                                        'I',
                                        'Discover',
                                        'Browse current and historical publications, then narrow the archive to the source and applicability you care about.',
                                    ],
                                    [
                                        'II',
                                        'Understand',
                                        'Compare the original with a structured interpretation in your preferred language.',
                                    ],
                                    [
                                        'III',
                                        'Act',
                                        'Save, export, receive updates—or open a version-bound chat when you need to investigate further.',
                                    ],
                                ].map(([number, title, copy]) => (
                                    <div
                                        key={number}
                                        className="grid gap-4 border-b border-white/15 py-8 sm:grid-cols-[4rem_10rem_1fr] sm:items-start"
                                    >
                                        <span className="font-mono text-sm text-white/35">
                                            {number}
                                        </span>
                                        <h3 className="text-2xl font-semibold">
                                            {title}
                                        </h3>
                                        <p className="leading-7 text-white/55">
                                            {copy}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-[1400px] px-6 py-28 lg:px-12 lg:py-40">
                    <div className="grid overflow-hidden rounded-[2rem] border border-black/10 lg:grid-cols-2 dark:border-white/10">
                        <div className="bg-gradient-to-br from-sky-600 to-teal-700 p-10 text-white md:p-16">
                            <ShieldCheck className="size-10" />
                            <h2 className="mt-20 text-5xl leading-[.96] font-semibold tracking-[-0.05em]">
                                AI that keeps the source in view.
                            </h2>
                            <p className="mt-7 max-w-lg text-lg leading-8 text-sky-100">
                                SahkarAI helps you read and investigate
                                regulatory material. It does not hide the
                                publication, blur versions together, or present
                                itself as professional advice.
                            </p>
                        </div>
                        <div className="bg-white p-10 md:p-16 dark:bg-slate-900">
                            {[
                                [
                                    'Original first',
                                    'Every archive entry preserves the source file and its metadata.',
                                ],
                                [
                                    'Version-bound answers',
                                    'Interpretations and chats point to one immutable document version.',
                                ],
                                [
                                    'Failure stays visible',
                                    'If processing is incomplete, the product says so instead of inventing a result.',
                                ],
                            ].map(([title, copy], index) => (
                                <div
                                    key={title}
                                    className="flex gap-5 border-b border-black/10 py-8 first:pt-0 last:border-0 last:pb-0 dark:border-white/10"
                                >
                                    <span className="grid size-8 shrink-0 place-items-center rounded-full bg-teal-100 text-xs font-semibold text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                                        0{index + 1}
                                    </span>
                                    <div>
                                        <h3 className="font-semibold">
                                            {title}
                                        </h3>
                                        <p className="mt-2 leading-7 text-slate-600 dark:text-slate-400">
                                            {copy}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section
                    id="pricing"
                    className="border-y border-black/5 bg-white py-28 lg:py-40 dark:border-white/10 dark:bg-slate-950"
                >
                    <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
                        <SectionLabel>Simple monthly pricing</SectionLabel>
                        <div className="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                            <h2 className="max-w-4xl text-5xl leading-[.98] font-semibold tracking-[-0.05em] md:text-7xl">
                                Start with the archive.
                                <br />
                                <span className="text-slate-400 dark:text-slate-600">
                                    Upgrade when clarity pays.
                                </span>
                            </h2>
                            <p className="max-w-sm leading-7 text-slate-600 dark:text-slate-400">
                                All prices are monthly and in INR. Explore for
                                free—no payment details needed to create an
                                account.
                            </p>
                        </div>
                        <div className="mt-20 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {plans.map((plan, index) => (
                                <article
                                    key={plan.name}
                                    className={`relative flex flex-col rounded-3xl border p-8 lg:p-10 ${plan.featured ? 'border-teal-600 bg-teal-600 text-white shadow-2xl shadow-teal-900/20' : 'border-black/10 bg-[#f7f7fb] dark:border-white/10 dark:bg-slate-900'}`}
                                >
                                    {plan.featured && (
                                        <span className="absolute -top-3 left-8 rounded-full bg-white px-3 py-1 text-[10px] font-bold tracking-[.16em] text-teal-700 uppercase">
                                            Most useful
                                        </span>
                                    )}
                                    <span
                                        className={`font-mono text-xs ${plan.featured ? 'text-teal-200' : 'text-slate-400'}`}
                                    >
                                        0{index + 1}
                                    </span>
                                    <h3 className="mt-6 text-3xl font-semibold">
                                        {plan.name}
                                    </h3>
                                    <p
                                        className={`mt-2 min-h-12 text-sm leading-6 ${plan.featured ? 'text-teal-100' : 'text-slate-500'}`}
                                    >
                                        {plan.description}
                                    </p>
                                    <div
                                        className={`my-8 border-y py-7 ${plan.featured ? 'border-white/20' : 'border-black/10 dark:border-white/10'}`}
                                    >
                                        <span className="text-5xl font-semibold tracking-[-.05em]">
                                            {plan.price}
                                        </span>
                                        <span
                                            className={`ml-2 text-sm ${plan.featured ? 'text-teal-200' : 'text-slate-500'}`}
                                        >
                                            / month
                                        </span>
                                    </div>
                                    <ul className="mb-10 space-y-4">
                                        {plan.features.map((item) => (
                                            <li
                                                key={item}
                                                className="flex gap-3 text-sm"
                                            >
                                                <Check className="mt-0.5 size-4 shrink-0" />
                                                <span
                                                    className={
                                                        plan.featured
                                                            ? 'text-teal-50'
                                                            : 'text-slate-600 dark:text-slate-300'
                                                    }
                                                >
                                                    {item}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                    <Button
                                        asChild
                                        variant={
                                            plan.featured
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        className="mt-auto h-12 rounded-full"
                                    >
                                        <Link href={primaryHref}>
                                            {auth.user
                                                ? 'View plan'
                                                : plan.name === 'Free'
                                                  ? 'Start free'
                                                  : 'Create an account'}{' '}
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-[1400px] px-6 py-24 lg:px-12 lg:py-32">
                    <div className="relative overflow-hidden rounded-[2.5rem] bg-slate-950 px-8 py-16 text-white md:px-16 md:py-24">
                        <div className="pointer-events-none absolute -right-32 -bottom-48 size-[34rem] rounded-full border-[80px] border-emerald-500/20" />
                        <div className="relative max-w-4xl">
                            <p className="text-xs font-semibold tracking-[.2em] text-emerald-300 uppercase">
                                Begin with the source
                            </p>
                            <h2 className="mt-6 text-5xl leading-[.94] font-semibold tracking-[-.055em] md:text-7xl">
                                The next update will arrive. Meet it prepared.
                            </h2>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300">
                                Create a free account and start building one
                                dependable place for the regulatory material
                                your co-operative follows.
                            </p>
                            <Button
                                asChild
                                size="lg"
                                className="mt-9 h-13 rounded-full bg-white px-7 text-slate-950 hover:bg-slate-100"
                            >
                                <Link href={primaryHref}>
                                    {auth.user
                                        ? 'Go to your archive'
                                        : 'Start exploring free'}{' '}
                                    <ArrowRight className="ml-2 size-4" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <footer className="border-t border-black/5 dark:border-white/10">
                    <div className="mx-auto flex max-w-[1400px] flex-col justify-between gap-8 px-6 py-10 md:flex-row md:items-center lg:px-12">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="h-9 w-14" />
                            <span className="font-semibold">SahkarAI</span>
                        </div>
                        <p className="max-w-xl text-xs leading-5 text-slate-500">
                            Educational regulatory information—not a substitute
                            for legal, tax, compliance or other professional
                            advice.
                        </p>
                        <div className="flex gap-6 text-sm text-slate-500">
                            <a href="#features" className="hover:text-teal-600">
                                Features
                            </a>
                            <a href="#pricing" className="hover:text-teal-600">
                                Pricing
                            </a>
                            <Link href="/login" className="hover:text-teal-600">
                                Sign in
                            </Link>
                        </div>
                    </div>
                </footer>
            </main>
        </>
    );
}
