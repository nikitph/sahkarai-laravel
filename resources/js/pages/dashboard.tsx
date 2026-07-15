import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    FolderKanban,
    Sparkles,
    UserPlus,
    UsersRound,
} from 'lucide-react';
import { motion } from 'motion/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Project = { id: number; name: string; status: string; created_at: string };
type Activity = { id: number; event: string; created_at: string };

export default function Dashboard({
    stats,
    recentProjects,
    recentActivity,
}: {
    stats: { projects: number; members: number; pendingInvitations: number };
    recentProjects: Project[];
    recentActivity: Activity[];
}) {
    const { auth, organization } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-8">
                <motion.section
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="relative overflow-hidden rounded-3xl border bg-gradient-to-br from-primary via-primary to-indigo-700 p-7 text-primary-foreground shadow-xl shadow-primary/10 md:p-10"
                >
                    <div className="absolute -top-20 -right-16 size-72 rounded-full bg-white/10 blur-3xl" />
                    <div className="relative max-w-2xl">
                        <Badge className="mb-5 border-white/20 bg-white/10 text-white hover:bg-white/10">
                            <Sparkles className="mr-1 size-3" />{' '}
                            {organization?.current?.name}
                        </Badge>
                        <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">
                            Good to see you, {firstName}.
                        </h1>
                        <p className="mt-3 max-w-xl text-sm leading-6 text-white/70 md:text-base">
                            Your SaaS foundation is live. Build the first piece
                            of product value from the Projects reference module.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <Button
                                asChild
                                variant="secondary"
                                className="rounded-xl"
                            >
                                <Link href="/projects">
                                    Open projects{' '}
                                    <ArrowRight className="ml-1 size-4" />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="ghost"
                                className="rounded-xl text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link href="/members">
                                    <UserPlus className="mr-1 size-4" /> Invite
                                    your team
                                </Link>
                            </Button>
                        </div>
                    </div>
                </motion.section>

                <section className="grid gap-4 md:grid-cols-3">
                    {[
                        {
                            label: 'Active projects',
                            value: stats.projects,
                            icon: FolderKanban,
                            tone: 'bg-violet-500/10 text-violet-600',
                        },
                        {
                            label: 'Workspace members',
                            value: stats.members,
                            icon: UsersRound,
                            tone: 'bg-emerald-500/10 text-emerald-600',
                        },
                        {
                            label: 'Pending invites',
                            value: stats.pendingInvitations,
                            icon: UserPlus,
                            tone: 'bg-amber-500/10 text-amber-600',
                        },
                    ].map((item, index) => (
                        <motion.div
                            key={item.label}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: 0.08 * index }}
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

                <section className="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
                    <Card className="rounded-2xl border-border/60 shadow-sm">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                Recent projects
                            </CardTitle>
                            <Button asChild variant="ghost" size="sm">
                                <Link href="/projects">View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {recentProjects.length ? (
                                recentProjects.map((project) => (
                                    <div
                                        key={project.id}
                                        className="flex items-center gap-3 rounded-xl border border-transparent p-3 transition hover:border-border hover:bg-muted/50"
                                    >
                                        <span className="grid size-9 place-items-center rounded-lg bg-primary/10 text-primary">
                                            <FolderKanban className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {project.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Created{' '}
                                                {new Date(
                                                    project.created_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            className="capitalize"
                                        >
                                            {project.status}
                                        </Badge>
                                    </div>
                                ))
                            ) : (
                                <Empty text="Create your first project to establish the product pattern." />
                            )}
                        </CardContent>
                    </Card>

                    <Card
                        id="activity"
                        className="rounded-2xl border-border/60 shadow-sm"
                    >
                        <CardHeader>
                            <CardTitle className="text-base">
                                Activity
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {recentActivity.length ? (
                                recentActivity.map((event) => (
                                    <div key={event.id} className="flex gap-3">
                                        <span className="mt-1 size-2 rounded-full bg-primary ring-4 ring-primary/10" />
                                        <div>
                                            <p className="text-sm font-medium">
                                                {event.event.replaceAll(
                                                    '.',
                                                    ' ',
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(
                                                    event.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <Empty text="Important changes will appear here." />
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

function Empty({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
            {text}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
