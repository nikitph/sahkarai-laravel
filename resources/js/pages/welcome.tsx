import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgePercent,
    BellRing,
    Bot,
    Building2,
    Check,
    FileCheck2,
    Languages,
    Search,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import {
    MotionConfig,
    animate,
    motion,
    useMotionValue,
    useMotionValueEvent,
    useReducedMotion,
    useScroll,
    useTransform,
} from 'motion/react';
import { useEffect, useState } from 'react';
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

const easeOut = [0.22, 1, 0.36, 1] as const;

const reveal = {
    hidden: { opacity: 0, y: 28 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.75, ease: easeOut },
    },
};

const stagger = {
    hidden: {},
    visible: {
        transition: {
            staggerChildren: 0.11,
            delayChildren: 0.08,
        },
    },
};

function ExtractionProgress() {
    const prefersReducedMotion = useReducedMotion();
    const progress = useMotionValue(prefersReducedMotion ? 100 : 0);
    const progressScale = useTransform(progress, [0, 100], [0, 1]);
    const [progressLabel, setProgressLabel] = useState(
        prefersReducedMotion ? 100 : 0,
    );

    useMotionValueEvent(progress, 'change', (latest) => {
        setProgressLabel(Math.round(latest));
    });

    useEffect(() => {
        if (prefersReducedMotion) {
            progress.set(100);

            return;
        }

        const controls = animate(progress, [0, 0, 28, 64, 100, 100, 0], {
            duration: 11,
            times: [0, 0.1, 0.3, 0.52, 0.72, 0.92, 1],
            ease: 'easeInOut',
            repeat: Infinity,
            repeatDelay: 0.6,
        });

        return () => controls.stop();
    }, [prefersReducedMotion, progress]);

    return (
        <div className="mt-auto border-t border-teal-900/10 pt-4 dark:border-white/10">
            <div className="flex items-center justify-between text-[10px]">
                <span className="text-slate-500">Extraction pipeline</span>
                <span className="min-w-8 text-right font-semibold text-teal-700 tabular-nums dark:text-teal-300">
                    {progressLabel}%
                </span>
            </div>
            <div className="mt-2 h-1 overflow-hidden rounded-full bg-teal-900/10">
                <motion.div
                    className="h-full origin-left rounded-full bg-teal-500"
                    style={{ scaleX: progressScale }}
                />
            </div>
        </div>
    );
}

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <motion.div
            variants={reveal}
            className="mb-7 flex items-center gap-4 text-[11px] font-semibold tracking-[0.22em] text-slate-500 uppercase dark:text-slate-400"
        >
            <motion.span
                variants={{
                    hidden: { scaleX: 0 },
                    visible: {
                        scaleX: 1,
                        transition: { duration: 0.6, ease: easeOut },
                    },
                }}
                className="h-px w-8 origin-left bg-current opacity-40"
            />
            {children}
        </motion.div>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;
    const primaryHref = auth.user ? '/archive' : '/register';
    const organizationHref = auth.user ? '/billing/team' : '/register';
    const prefersReducedMotion = useReducedMotion();
    const { scrollYProgress } = useScroll();
    const progressScale = useTransform(
        scrollYProgress,
        [0, 1],
        prefersReducedMotion ? [0, 0] : [0, 1],
    );

    return (
        <MotionConfig reducedMotion="user">
            <Head title="Regulatory intelligence for Indian co-operatives" />
            <main className="min-h-screen overflow-hidden bg-[#f7f7fb] text-[#17171d] selection:bg-sky-200 dark:bg-[#0b0b11] dark:text-slate-50 dark:selection:bg-teal-900">
                <motion.div
                    aria-hidden="true"
                    style={{ scaleX: progressScale }}
                    className="fixed inset-x-0 top-0 z-[60] h-0.5 origin-left bg-gradient-to-r from-sky-500 via-teal-500 to-emerald-500"
                />
                <motion.header
                    initial={{ opacity: 0, y: -14 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, ease: easeOut }}
                    className="sticky top-0 z-50 border-b border-black/5 bg-[#f7f7fb]/85 backdrop-blur-xl dark:border-white/10 dark:bg-[#0b0b11]/85"
                >
                    <nav className="mx-auto flex h-20 max-w-[1400px] items-center justify-between px-6 lg:px-12">
                        <Link
                            href="/"
                            className="landing-brand flex items-center gap-2.5 font-semibold tracking-tight"
                        >
                            <AppLogoIcon className="h-11 w-16" />
                            <span className="text-lg">SahkarAI</span>
                        </Link>
                        <div className="hidden items-center gap-9 text-sm text-slate-600 md:flex dark:text-slate-300">
                            <a
                                href="#features"
                                className="landing-nav-link transition-colors hover:text-teal-600"
                            >
                                Features
                            </a>
                            <a
                                href="#workflow"
                                className="landing-nav-link transition-colors hover:text-teal-600"
                            >
                                How it works
                            </a>
                            <a
                                href="#pricing"
                                className="landing-nav-link transition-colors hover:text-teal-600"
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
                                        className="landing-cta rounded-full px-5"
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
                </motion.header>

                <section className="relative border-b border-black/5 dark:border-white/10">
                    <motion.div
                        className="pointer-events-none absolute inset-0 opacity-45 dark:opacity-20"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, rgba(13,148,136,.09) 1px, transparent 1px), linear-gradient(to bottom, rgba(13,148,136,.09) 1px, transparent 1px)',
                            backgroundSize: '72px 72px',
                            maskImage:
                                'linear-gradient(to bottom, black, transparent 88%)',
                        }}
                        animate={
                            prefersReducedMotion
                                ? undefined
                                : {
                                      backgroundPosition: [
                                          '0px 0px',
                                          '72px 72px',
                                      ],
                                  }
                        }
                        transition={{
                            duration: 18,
                            ease: 'linear',
                            repeat: Infinity,
                        }}
                    />
                    <motion.div
                        className="pointer-events-none absolute -top-48 right-[-12rem] size-[44rem] rounded-full bg-emerald-400/15 blur-[130px]"
                        animate={
                            prefersReducedMotion
                                ? undefined
                                : {
                                      x: [0, -36, 16, 0],
                                      y: [0, 24, -14, 0],
                                      scale: [1, 1.08, 0.97, 1],
                                  }
                        }
                        transition={{
                            duration: 20,
                            ease: 'easeInOut',
                            repeat: Infinity,
                        }}
                    />
                    <motion.div
                        aria-hidden="true"
                        className="pointer-events-none absolute -bottom-56 -left-56 size-[34rem] rounded-full bg-sky-400/10 blur-[120px]"
                        animate={
                            prefersReducedMotion
                                ? undefined
                                : {
                                      x: [0, 42, 0],
                                      y: [0, -24, 0],
                                      scale: [1, 1.12, 1],
                                  }
                        }
                        transition={{
                            duration: 16,
                            ease: 'easeInOut',
                            repeat: Infinity,
                        }}
                    />
                    <div className="relative mx-auto grid min-h-[calc(100vh-5rem)] max-w-[1400px] items-center gap-16 px-6 py-20 lg:grid-cols-[1.12fr_.88fr] lg:px-12 lg:py-28">
                        <motion.div
                            variants={stagger}
                            initial="hidden"
                            animate="visible"
                        >
                            <SectionLabel>
                                Regulatory intelligence for Indian co-operatives
                            </SectionLabel>
                            <motion.h1
                                variants={reveal}
                                className="max-w-4xl text-[clamp(4rem,9vw,8.7rem)] leading-[0.83] font-semibold tracking-[-0.07em] text-balance"
                            >
                                From circular
                                <motion.span
                                    className="landing-gradient-text block bg-gradient-to-r from-sky-600 via-teal-600 to-emerald-600 bg-clip-text pb-3 text-transparent"
                                    animate={
                                        prefersReducedMotion
                                            ? undefined
                                            : {
                                                  backgroundPositionX: [
                                                      '0%',
                                                      '100%',
                                                      '0%',
                                                  ],
                                              }
                                    }
                                    transition={{
                                        duration: 9,
                                        ease: 'easeInOut',
                                        repeat: Infinity,
                                    }}
                                >
                                    to clarity.
                                </motion.span>
                            </motion.h1>
                            <motion.div
                                variants={reveal}
                                className="mt-10 grid max-w-3xl gap-8 border-t border-black/10 pt-8 sm:grid-cols-[1fr_auto] sm:items-end dark:border-white/10"
                            >
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
                                        className="landing-cta h-13 rounded-full px-7 shadow-lg shadow-teal-900/15"
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
                            </motion.div>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, x: 24, rotate: 1 }}
                            animate={{ opacity: 1, x: 0, rotate: 0 }}
                            transition={{
                                delay: 0.18,
                                duration: 0.9,
                                ease: easeOut,
                            }}
                            className="relative mx-auto w-full max-w-xl"
                        >
                            <motion.div
                                className="absolute inset-8 rounded-[2.5rem] bg-teal-500/20 blur-3xl"
                                animate={
                                    prefersReducedMotion
                                        ? undefined
                                        : {
                                              opacity: [0.45, 0.8, 0.45],
                                              scale: [0.96, 1.06, 0.96],
                                          }
                                }
                                transition={{
                                    duration: 7,
                                    ease: 'easeInOut',
                                    repeat: Infinity,
                                }}
                            />
                            <motion.div className="relative overflow-hidden rounded-[2rem] border border-white/60 bg-white/90 p-2 shadow-[0_35px_100px_-40px_rgba(15,118,110,.4)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/90">
                                <div className="flex items-center justify-between gap-4 px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-9 place-items-center rounded-xl bg-slate-950 text-sky-300 dark:bg-black">
                                            <FileCheck2 className="size-4" />
                                        </span>
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-[0.2em] text-teal-600 uppercase">
                                                Latest publication
                                            </p>
                                            <p className="mt-0.5 text-sm font-semibold">
                                                RBI regulatory update
                                            </p>
                                        </div>
                                    </div>
                                    <span className="flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                        <motion.span
                                            className="size-1.5 rounded-full bg-emerald-500"
                                            animate={
                                                prefersReducedMotion
                                                    ? undefined
                                                    : {
                                                          opacity: [
                                                              0.45, 1, 0.45,
                                                          ],
                                                          scale: [
                                                              0.8, 1.15, 0.8,
                                                          ],
                                                      }
                                            }
                                            transition={{
                                                duration: 2.4,
                                                ease: 'easeInOut',
                                                repeat: Infinity,
                                            }}
                                        />
                                        Source preserved
                                    </span>
                                </div>
                                <div className="grid gap-2 sm:grid-cols-[1.04fr_.96fr]">
                                    <motion.div
                                        className="rounded-[1.35rem] bg-slate-950 p-4 text-white"
                                        animate={
                                            prefersReducedMotion
                                                ? { opacity: 1, scale: 1 }
                                                : {
                                                      opacity: [0, 1, 1, 1, 0],
                                                      scale: [
                                                          0.985, 1, 1, 1, 0.99,
                                                      ],
                                                  }
                                        }
                                        transition={{
                                            duration: 11,
                                            times: [0, 0.08, 0.9, 0.97, 1],
                                            ease: easeOut,
                                            repeat: prefersReducedMotion
                                                ? 0
                                                : Infinity,
                                            repeatDelay: 0.6,
                                        }}
                                    >
                                        <div className="mb-3 flex items-center justify-between">
                                            <div>
                                                <p className="text-[9px] font-semibold tracking-[0.16em] text-sky-300 uppercase">
                                                    Original PDF
                                                </p>
                                                <p className="mt-1 text-xs text-slate-400">
                                                    Circular · 12 pages
                                                </p>
                                            </div>
                                            <span className="rounded-full border border-white/10 px-2.5 py-1 font-mono text-[9px] text-slate-400">
                                                v01
                                            </span>
                                        </div>

                                        <motion.div
                                            className="relative min-h-72 overflow-hidden rounded-xl bg-[#f8fafc] p-5 text-slate-950 shadow-2xl shadow-black/40"
                                            animate={
                                                prefersReducedMotion
                                                    ? { y: 0, opacity: 1 }
                                                    : {
                                                          y: [8, 0, 0, 0, 4],
                                                          opacity: [
                                                              0, 1, 1, 1, 0,
                                                          ],
                                                      }
                                            }
                                            transition={{
                                                duration: 11,
                                                times: [0, 0.11, 0.9, 0.97, 1],
                                                ease: easeOut,
                                                repeat: prefersReducedMotion
                                                    ? 0
                                                    : Infinity,
                                                repeatDelay: 0.6,
                                            }}
                                        >
                                            <div className="flex items-start justify-between border-b border-slate-200 pb-4">
                                                <div className="space-y-1.5">
                                                    <div className="h-2 w-20 rounded-full bg-slate-900" />
                                                    <div className="h-1.5 w-14 rounded-full bg-slate-300" />
                                                </div>
                                                <div className="grid size-8 place-items-center rounded-full border-2 border-sky-600 text-[7px] font-bold tracking-wider text-sky-700">
                                                    RBI
                                                </div>
                                            </div>
                                            <div className="mt-5">
                                                <p className="font-mono text-[7px] tracking-[0.16em] text-slate-400 uppercase">
                                                    Regulatory circular
                                                </p>
                                                <div className="mt-2 h-2.5 w-4/5 rounded-full bg-slate-800" />
                                                <div className="mt-2 h-2.5 w-3/5 rounded-full bg-slate-800" />
                                            </div>

                                            <div className="mt-6 rounded-lg border-l-2 border-teal-500 bg-teal-50 p-3">
                                                <div className="h-1.5 w-full rounded-full bg-teal-900/55" />
                                                <div className="mt-2 h-1.5 w-11/12 rounded-full bg-teal-900/30" />
                                                <div className="mt-2 h-1.5 w-3/4 rounded-full bg-teal-900/30" />
                                            </div>

                                            <div className="mt-5 space-y-2">
                                                {[92, 100, 84, 96, 68].map(
                                                    (width) => (
                                                        <span
                                                            key={width}
                                                            className="block h-1.5 rounded-full bg-slate-200"
                                                            style={{
                                                                width: `${width}%`,
                                                            }}
                                                        />
                                                    ),
                                                )}
                                            </div>

                                            <div className="absolute right-4 bottom-4 rounded border border-sky-700/30 px-2 py-1 font-mono text-[7px] tracking-wider text-sky-700 uppercase">
                                                Source copy
                                            </div>
                                            <motion.div
                                                aria-hidden="true"
                                                className="pointer-events-none absolute inset-x-0 top-0 h-16 border-b border-teal-400/70 bg-gradient-to-b from-transparent via-teal-300/20 to-teal-400/10"
                                                animate={
                                                    prefersReducedMotion
                                                        ? { opacity: 0 }
                                                        : {
                                                              y: [
                                                                  -72, -72, 255,
                                                                  255, -72,
                                                              ],
                                                              opacity: [
                                                                  0, 0.9, 0.9,
                                                                  0, 0,
                                                              ],
                                                          }
                                                }
                                                transition={{
                                                    duration: 11,
                                                    times: [
                                                        0, 0.13, 0.47, 0.55, 1,
                                                    ],
                                                    ease: 'easeInOut',
                                                    repeat: prefersReducedMotion
                                                        ? 0
                                                        : Infinity,
                                                    repeatDelay: 0.6,
                                                }}
                                            />
                                        </motion.div>
                                    </motion.div>

                                    <div className="flex flex-col rounded-[1.35rem] bg-teal-50 p-4 dark:bg-teal-950/50">
                                        <motion.div
                                            className="flex items-start justify-between gap-3"
                                            animate={
                                                prefersReducedMotion
                                                    ? { opacity: 1 }
                                                    : {
                                                          opacity: [
                                                              0, 0, 1, 1, 0,
                                                          ],
                                                      }
                                            }
                                            transition={{
                                                duration: 11,
                                                times: [0, 0.28, 0.36, 0.95, 1],
                                                ease: easeOut,
                                                repeat: prefersReducedMotion
                                                    ? 0
                                                    : Infinity,
                                                repeatDelay: 0.6,
                                            }}
                                        >
                                            <div>
                                                <span className="text-[9px] font-semibold tracking-[0.16em] text-teal-700 uppercase dark:text-teal-300">
                                                    Interpretation
                                                </span>
                                                <p className="mt-1 text-[10px] text-slate-500">
                                                    Plain-language brief
                                                </p>
                                            </div>
                                            <span className="text-[10px] text-slate-500">
                                                EN · HI · GU · MR
                                            </span>
                                        </motion.div>
                                        <motion.h2
                                            className="mt-7 text-xl leading-tight font-semibold tracking-tight"
                                            animate={
                                                prefersReducedMotion
                                                    ? { opacity: 1, x: 0 }
                                                    : {
                                                          opacity: [
                                                              0, 0, 1, 1, 0,
                                                          ],
                                                          x: [10, 10, 0, 0, 6],
                                                      }
                                            }
                                            transition={{
                                                duration: 11,
                                                times: [0, 0.34, 0.43, 0.95, 1],
                                                ease: easeOut,
                                                repeat: prefersReducedMotion
                                                    ? 0
                                                    : Infinity,
                                                repeatDelay: 0.6,
                                            }}
                                        >
                                            What changed—and what to review
                                            next.
                                        </motion.h2>
                                        <div className="mt-5 space-y-2">
                                            {[
                                                'Scope and applicability',
                                                'Key dates and obligations',
                                                'Questions to take to your adviser',
                                            ].map((item, index) => (
                                                <motion.div
                                                    key={item}
                                                    className="flex items-start gap-3 rounded-xl border border-teal-900/5 bg-white/75 p-3 text-xs shadow-sm shadow-teal-950/5 dark:border-white/5 dark:bg-slate-900/60"
                                                    initial={{
                                                        opacity: 0,
                                                        x: 10,
                                                    }}
                                                    animate={{
                                                        opacity:
                                                            prefersReducedMotion
                                                                ? 1
                                                                : [
                                                                      0, 0, 1,
                                                                      1, 0,
                                                                  ],
                                                        x: prefersReducedMotion
                                                            ? 0
                                                            : [10, 10, 0, 0, 6],
                                                    }}
                                                    transition={{
                                                        duration: 11,
                                                        times: prefersReducedMotion
                                                            ? undefined
                                                            : [
                                                                  0,
                                                                  0.42 +
                                                                      index *
                                                                          0.07,
                                                                  0.5 +
                                                                      index *
                                                                          0.07,
                                                                  0.95,
                                                                  1,
                                                              ],
                                                        ease: easeOut,
                                                        repeat: prefersReducedMotion
                                                            ? 0
                                                            : Infinity,
                                                        repeatDelay: 0.6,
                                                    }}
                                                >
                                                    <span className="grid size-5 shrink-0 place-items-center rounded-full bg-teal-600 text-[10px] text-white">
                                                        {index + 1}
                                                    </span>
                                                    <span className="pt-0.5 text-slate-700 dark:text-slate-200">
                                                        {item}
                                                    </span>
                                                </motion.div>
                                            ))}
                                        </div>
                                        <ExtractionProgress />
                                    </div>
                                </div>
                            </motion.div>
                        </motion.div>
                    </div>
                </section>

                <section className="border-b border-black/5 dark:border-white/10">
                    <motion.div
                        variants={stagger}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true, amount: 0.5 }}
                        className="mx-auto grid max-w-[1400px] grid-cols-3 px-6 lg:px-12"
                    >
                        {[
                            'Reserve Bank of India',
                            'Income Tax',
                            'Goods & Services Tax',
                        ].map((source, index) => (
                            <motion.div
                                key={source}
                                variants={reveal}
                                className="border-x border-black/5 px-3 py-7 text-center dark:border-white/10"
                            >
                                <span className="mr-2 text-xs text-slate-400">
                                    0{index + 1}
                                </span>
                                <span className="text-xs font-semibold tracking-wide sm:text-sm">
                                    {source}
                                </span>
                            </motion.div>
                        ))}
                    </motion.div>
                </section>

                <section
                    id="features"
                    className="mx-auto max-w-[1400px] px-6 py-28 lg:px-12 lg:py-40"
                >
                    <motion.div
                        variants={stagger}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true, amount: 0.3 }}
                    >
                        <SectionLabel>
                            Built for the work after the update lands
                        </SectionLabel>
                        <motion.h2
                            variants={reveal}
                            className="max-w-5xl text-5xl leading-[0.98] font-semibold tracking-[-0.05em] text-balance md:text-7xl"
                        >
                            Less time decoding.
                            <br />
                            <span className="text-slate-400 dark:text-slate-600">
                                More confidence deciding.
                            </span>
                        </motion.h2>
                    </motion.div>
                    <motion.div
                        variants={stagger}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true, amount: 0.18 }}
                        className="mt-20 grid gap-px overflow-hidden rounded-3xl border border-black/10 bg-black/10 md:grid-cols-2 dark:border-white/10 dark:bg-white/10"
                    >
                        {features.map((feature, index) => (
                            <motion.article
                                key={feature.title}
                                variants={reveal}
                                whileHover={
                                    prefersReducedMotion
                                        ? undefined
                                        : { y: -4, scale: 1.006 }
                                }
                                transition={{
                                    duration: 0.35,
                                    ease: easeOut,
                                }}
                                className="group relative bg-[#f7f7fb] p-8 transition-colors hover:bg-white md:p-12 dark:bg-[#0b0b11] dark:hover:bg-slate-900"
                            >
                                <motion.span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-x-0 top-0 h-px origin-left bg-gradient-to-r from-transparent via-teal-500/70 to-transparent"
                                    initial={{ scaleX: 0 }}
                                    whileInView={{ scaleX: 1 }}
                                    viewport={{ once: true }}
                                    transition={{
                                        delay: 0.2 + index * 0.08,
                                        duration: 0.8,
                                        ease: easeOut,
                                    }}
                                />
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
                            </motion.article>
                        ))}
                    </motion.div>
                </section>

                <section
                    id="workflow"
                    className="relative overflow-hidden bg-slate-950 py-28 text-white lg:py-40"
                >
                    <motion.div
                        className="pointer-events-none absolute inset-0 opacity-10"
                        style={{
                            backgroundImage:
                                'repeating-linear-gradient(-45deg, transparent, transparent 44px, white 45px)',
                        }}
                        animate={
                            prefersReducedMotion
                                ? undefined
                                : {
                                      backgroundPosition: [
                                          '0px 0px',
                                          '90px 0px',
                                      ],
                                  }
                        }
                        transition={{
                            duration: 16,
                            ease: 'linear',
                            repeat: Infinity,
                        }}
                    />
                    <motion.div
                        variants={stagger}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true, amount: 0.12 }}
                        className="relative mx-auto max-w-[1400px] px-6 lg:px-12"
                    >
                        <SectionLabel>How it works</SectionLabel>
                        <div className="grid gap-16 lg:grid-cols-[.8fr_1.2fr] lg:gap-24">
                            <motion.h2
                                variants={reveal}
                                className="text-5xl leading-[.95] font-semibold tracking-[-0.055em] md:text-7xl"
                            >
                                Three steps.
                                <br />
                                <span className="text-white/35">
                                    One source of truth.
                                </span>
                            </motion.h2>
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
                                    <motion.div
                                        key={number}
                                        variants={reveal}
                                        whileHover={
                                            prefersReducedMotion
                                                ? undefined
                                                : { x: 8 }
                                        }
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
                                    </motion.div>
                                ))}
                            </div>
                        </div>
                    </motion.div>
                </section>

                <section className="mx-auto max-w-[1400px] px-6 py-28 lg:px-12 lg:py-40">
                    <motion.div
                        initial={{ opacity: 0, y: 32 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true, amount: 0.25 }}
                        transition={{ duration: 0.8, ease: easeOut }}
                        className="grid overflow-hidden rounded-[2rem] border border-black/10 lg:grid-cols-2 dark:border-white/10"
                    >
                        <motion.div
                            className="relative overflow-hidden bg-gradient-to-br from-sky-600 to-teal-700 p-10 text-white md:p-16"
                            whileHover={
                                prefersReducedMotion
                                    ? undefined
                                    : { backgroundPosition: '100% 100%' }
                            }
                            transition={{ duration: 1.2, ease: easeOut }}
                            style={{ backgroundSize: '160% 160%' }}
                        >
                            <motion.div
                                aria-hidden="true"
                                className="absolute -top-28 -right-28 size-64 rounded-full border border-white/20"
                                animate={
                                    prefersReducedMotion
                                        ? undefined
                                        : { rotate: 360, scale: [1, 1.08, 1] }
                                }
                                transition={{
                                    rotate: {
                                        duration: 28,
                                        ease: 'linear',
                                        repeat: Infinity,
                                    },
                                    scale: {
                                        duration: 8,
                                        ease: 'easeInOut',
                                        repeat: Infinity,
                                    },
                                }}
                            />
                            <motion.div
                                whileHover={
                                    prefersReducedMotion
                                        ? undefined
                                        : { rotate: -8, scale: 1.08 }
                                }
                                className="relative w-fit"
                            >
                                <ShieldCheck className="size-10" />
                            </motion.div>
                            <h2 className="mt-20 text-5xl leading-[.96] font-semibold tracking-[-0.05em]">
                                AI that keeps the source in view.
                            </h2>
                            <p className="mt-7 max-w-lg text-lg leading-8 text-sky-100">
                                SahkarAI helps you read and investigate
                                regulatory material. It does not hide the
                                publication, blur versions together, or present
                                itself as professional advice.
                            </p>
                        </motion.div>
                        <motion.div
                            variants={stagger}
                            initial="hidden"
                            whileInView="visible"
                            viewport={{ once: true, amount: 0.35 }}
                            className="bg-white p-10 md:p-16 dark:bg-slate-900"
                        >
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
                                <motion.div
                                    key={title}
                                    variants={reveal}
                                    whileHover={
                                        prefersReducedMotion
                                            ? undefined
                                            : { x: 6 }
                                    }
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
                                </motion.div>
                            ))}
                        </motion.div>
                    </motion.div>
                </section>

                <section
                    id="pricing"
                    className="border-y border-black/5 bg-white py-28 lg:py-40 dark:border-white/10 dark:bg-slate-950"
                >
                    <motion.div
                        variants={stagger}
                        initial="hidden"
                        whileInView="visible"
                        viewport={{ once: true, amount: 0.1 }}
                        className="mx-auto max-w-[1400px] px-6 lg:px-12"
                    >
                        <SectionLabel>Simple monthly pricing</SectionLabel>
                        <motion.div
                            variants={reveal}
                            className="flex flex-col justify-between gap-8 lg:flex-row lg:items-end"
                        >
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
                        </motion.div>
                        <motion.div
                            variants={stagger}
                            className="mt-20 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                        >
                            {plans.map((plan, index) => (
                                <motion.article
                                    key={plan.name}
                                    variants={reveal}
                                    whileHover={
                                        prefersReducedMotion
                                            ? undefined
                                            : {
                                                  y: plan.featured ? -10 : -7,
                                                  scale: plan.featured
                                                      ? 1.018
                                                      : 1.01,
                                              }
                                    }
                                    transition={{
                                        duration: 0.35,
                                        ease: easeOut,
                                    }}
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
                                </motion.article>
                            ))}
                        </motion.div>
                        <motion.aside
                            variants={reveal}
                            className="mt-6 overflow-hidden rounded-3xl border border-teal-900/10 bg-teal-50 dark:border-teal-300/15 dark:bg-teal-950/40"
                        >
                            <div className="grid lg:grid-cols-[minmax(0,1.2fr)_minmax(28rem,.8fr)]">
                                <div className="p-8 md:p-10 lg:p-12">
                                    <div className="flex items-center gap-2 text-xs font-semibold tracking-[.16em] text-teal-700 uppercase dark:text-teal-300">
                                        <Building2 className="size-4" />
                                        Organization plans
                                    </div>
                                    <h3 className="mt-5 max-w-2xl text-3xl leading-tight font-semibold tracking-[-0.035em] md:text-4xl">
                                        One plan for your whole team.
                                    </h3>
                                    <p className="mt-4 max-w-2xl leading-7 text-slate-600 dark:text-slate-300">
                                        Choose any paid tier for 2–25 seats.
                                        Every active member gets the selected
                                        plan, while the owner can invite people
                                        and administer seat access.
                                    </p>
                                    <Button
                                        asChild
                                        className="mt-7 h-12 rounded-full px-6"
                                    >
                                        <Link href={organizationHref}>
                                            Choose an organization plan
                                            <ArrowRight className="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                </div>
                                <div className="grid border-t border-teal-900/10 sm:grid-cols-3 lg:border-t-0 lg:border-l dark:border-teal-300/15">
                                    {[
                                        {
                                            icon: UsersRound,
                                            value: '2–25',
                                            label: 'seats per organization',
                                        },
                                        {
                                            icon: BadgePercent,
                                            value: '5–25%',
                                            label: 'automatic volume discount',
                                        },
                                        {
                                            icon: Check,
                                            value: 'Per seat',
                                            label: 'simple monthly billing',
                                        },
                                    ].map((item) => (
                                        <div
                                            key={item.value}
                                            className="border-b border-teal-900/10 p-7 last:border-b-0 sm:border-r sm:border-b-0 sm:last:border-r-0 lg:flex lg:flex-col lg:justify-center dark:border-teal-300/15"
                                        >
                                            <item.icon className="size-5 text-teal-700 dark:text-teal-300" />
                                            <div className="mt-5 text-2xl font-semibold tracking-tight">
                                                {item.value}
                                            </div>
                                            <div className="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400">
                                                {item.label}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </motion.aside>
                    </motion.div>
                </section>

                <section className="mx-auto max-w-[1400px] px-6 py-24 lg:px-12 lg:py-32">
                    <motion.div
                        initial={{ opacity: 0, y: 32, scale: 0.985 }}
                        whileInView={{ opacity: 1, y: 0, scale: 1 }}
                        viewport={{ once: true, amount: 0.25 }}
                        transition={{ duration: 0.85, ease: easeOut }}
                        className="relative overflow-hidden rounded-[2.5rem] bg-slate-950 px-8 py-16 text-white md:px-16 md:py-24"
                    >
                        <motion.div
                            className="pointer-events-none absolute -right-32 -bottom-48 size-[34rem] rounded-full border-[80px] border-emerald-500/20"
                            animate={
                                prefersReducedMotion
                                    ? undefined
                                    : {
                                          rotate: 360,
                                          scale: [1, 1.08, 1],
                                      }
                            }
                            transition={{
                                rotate: {
                                    duration: 32,
                                    ease: 'linear',
                                    repeat: Infinity,
                                },
                                scale: {
                                    duration: 9,
                                    ease: 'easeInOut',
                                    repeat: Infinity,
                                },
                            }}
                        />
                        <motion.div
                            variants={stagger}
                            initial="hidden"
                            whileInView="visible"
                            viewport={{ once: true, amount: 0.45 }}
                            className="relative max-w-4xl"
                        >
                            <p className="text-xs font-semibold tracking-[.2em] text-emerald-300 uppercase">
                                Begin with the source
                            </p>
                            <motion.h2
                                variants={reveal}
                                className="mt-6 text-5xl leading-[.94] font-semibold tracking-[-.055em] md:text-7xl"
                            >
                                The next update will arrive. Meet it prepared.
                            </motion.h2>
                            <motion.p
                                variants={reveal}
                                className="mt-7 max-w-2xl text-lg leading-8 text-slate-300"
                            >
                                Create a free account and start building one
                                dependable place for the regulatory material
                                your co-operative follows.
                            </motion.p>
                            <motion.div variants={reveal}>
                                <Button
                                    asChild
                                    size="lg"
                                    className="landing-cta mt-9 h-13 rounded-full bg-white px-7 text-slate-950 hover:bg-slate-100"
                                >
                                    <Link href={primaryHref}>
                                        {auth.user
                                            ? 'Go to your archive'
                                            : 'Start exploring free'}{' '}
                                        <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                            </motion.div>
                        </motion.div>
                    </motion.div>
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
                            <a
                                href="#features"
                                className="landing-nav-link hover:text-teal-600"
                            >
                                Features
                            </a>
                            <a
                                href="#pricing"
                                className="landing-nav-link hover:text-teal-600"
                            >
                                Pricing
                            </a>
                            <Link
                                href="/login"
                                className="landing-nav-link hover:text-teal-600"
                            >
                                Sign in
                            </Link>
                        </div>
                    </div>
                </footer>
            </main>
        </MotionConfig>
    );
}
