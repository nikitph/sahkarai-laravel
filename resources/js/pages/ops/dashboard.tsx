import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    DatabaseZap,
    FileWarning,
    Gauge,
    Search,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Source = {
    source: string;
    last_success: { completed_at: string } | null;
    last_failure: { completed_at: string; error: string } | null;
};
type Issue = {
    id: number;
    category: string;
    status: string;
    details: string;
    created_at: string;
    user: { name: string; email: string } | null;
    interpretation: { version: { document: { title: string } } };
};
type Alert = {
    id: number;
    severity: string;
    title: string;
    details: string;
    created_at: string;
};
type User = {
    id: number;
    name: string;
    email: string;
    tier: string;
    role: string;
    created_at: string;
};
export default function OpsDashboard({
    sources,
    counts,
    issues,
    alerts,
    users,
}: {
    sources: Source[];
    counts: Record<string, number>;
    issues: Issue[];
    alerts: Alert[];
    users: User[];
}) {
    const [q, setQ] = useState('');
    const search = (e: FormEvent) => {
        e.preventDefault();
        router.get('/ops', { q }, { preserveState: true });
    };

    return (
        <>
            <Head title="Operations" />
            <div className="mx-auto w-full max-w-7xl p-4 md:p-8">
                <div className="mb-7">
                    <Badge variant="secondary" className="mb-3">
                        <Gauge className="mr-1 size-3" /> SaaS admin only
                    </Badge>
                    <h1 className="text-3xl font-semibold">
                        Operations control room
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Corpus health, failed automation and support triage.
                        Private chats and notification bodies are deliberately
                        absent.
                    </p>
                </div>
                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        {
                            label: 'Extraction failures',
                            value: counts.extractionFailures,
                            icon: FileWarning,
                        },
                        {
                            label: 'Interpretation failures',
                            value: counts.interpretationFailures,
                            icon: AlertTriangle,
                        },
                        {
                            label: 'Open issues',
                            value: counts.openIssues,
                            icon: DatabaseZap,
                        },
                        {
                            label: 'Open alerts',
                            value: counts.openAlerts,
                            icon: Gauge,
                        },
                    ].map((item) => (
                        <Card key={item.label} className="rounded-2xl">
                            <CardContent className="flex items-center gap-4 p-5">
                                <span className="grid size-10 place-items-center rounded-xl bg-amber-500/10 text-amber-600">
                                    <item.icon className="size-5" />
                                </span>
                                <div>
                                    <p className="text-2xl font-semibold">
                                        {item.value}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.label}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <section className="mt-6 grid gap-4 md:grid-cols-3">
                    {sources.map((source) => (
                        <Card key={source.source} className="rounded-2xl">
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between text-base uppercase">
                                    {source.source.replace('_', ' ')}{' '}
                                    {source.last_success ? (
                                        <CheckCircle2 className="size-5 text-emerald-600" />
                                    ) : (
                                        <AlertTriangle className="size-5 text-amber-600" />
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                <p>Last success</p>
                                <p className="font-medium">
                                    {source.last_success
                                        ? new Date(
                                              source.last_success.completed_at,
                                          ).toLocaleString()
                                        : 'Never'}
                                </p>
                                {source.last_failure && (
                                    <p className="mt-2 line-clamp-2 text-xs text-red-600">
                                        {source.last_failure.error}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    <Card className="rounded-2xl">
                        <CardHeader>
                            <CardTitle>Issue reports</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {issues.map((issue) => (
                                <div
                                    key={issue.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {
                                                    issue.interpretation.version
                                                        .document.title
                                                }
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {issue.user?.email ??
                                                    'Anonymized user'}{' '}
                                                · {issue.category}
                                            </p>
                                        </div>
                                        <Badge variant="outline">
                                            {issue.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {issue.details}
                                    </p>
                                    {issue.status !== 'resolved' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="mt-3"
                                            onClick={() =>
                                                router.patch(
                                                    `/ops/issues/${issue.id}`,
                                                    {
                                                        status: 'resolved',
                                                        resolution:
                                                            'Reviewed by operations.',
                                                    },
                                                )
                                            }
                                        >
                                            Resolve
                                        </Button>
                                    )}
                                </div>
                            ))}
                            {!issues.length && (
                                <p className="text-sm text-muted-foreground">
                                    No issue reports.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl">
                        <CardHeader>
                            <CardTitle>Operational alerts</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {alerts.map((alert) => (
                                <div
                                    key={alert.id}
                                    className="rounded-xl border border-red-200 bg-red-50/40 p-4 dark:bg-red-950/10"
                                >
                                    <div className="flex justify-between">
                                        <p className="font-medium">
                                            {alert.title}
                                        </p>
                                        <Badge variant="outline">
                                            {alert.severity}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {alert.details}
                                    </p>
                                </div>
                            ))}
                            {!alerts.length && (
                                <p className="text-sm text-muted-foreground">
                                    No active operational alerts.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <Card className="mt-6 rounded-2xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="size-5" /> User lookup
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={search}
                            className="mb-4 flex max-w-lg gap-2"
                        >
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Name or email"
                            />
                            <Button>
                                <Search className="size-4" />
                            </Button>
                        </form>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="py-2">User</th>
                                        <th>Tier</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3">
                                                <p className="font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </td>
                                            <td className="capitalize">
                                                {user.tier.replace('_', ' ')}
                                            </td>
                                            <td>{user.role}</td>
                                            <td>
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
OpsDashboard.layout = { breadcrumbs: [{ title: 'Operations', href: '/ops' }] };
