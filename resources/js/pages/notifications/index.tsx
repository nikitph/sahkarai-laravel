import { Head, router, useForm } from '@inertiajs/react';
import { Bell, CheckCheck, Mail, Radio } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/lib/i18n';

type Notice = {
    id: string;
    title: string;
    body: string;
    type: string;
    data: { document_id?: number };
    read_at: string | null;
    created_at: string;
};
type Prefs = {
    source_rbi: boolean;
    source_income_tax: boolean;
    source_gst: boolean;
    email_enabled: boolean;
    source_rbi_cadence: string;
    source_income_tax_cadence: string;
    source_gst_cadence: string;
};
export default function Notifications({
    notifications,
    preferences,
}: {
    notifications: { data: Notice[] };
    preferences: Prefs;
}) {
    const t = useT();
    const form = useForm({ ...preferences });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch('/notifications/preferences');
    };

    return (
        <>
            <Head title={t('notifications')} />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 md:p-8 lg:grid-cols-[1fr_340px]">
                <main>
                    <div className="mb-6 flex items-end justify-between">
                        <div>
                            <Badge variant="secondary" className="mb-3">
                                <Bell className="mr-1 size-3" /> Your regulatory
                                watchlist
                            </Badge>
                            <h1 className="text-3xl font-semibold">
                                {t('notifications')}
                            </h1>
                        </div>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch('/notifications/read-all')
                            }
                        >
                            <CheckCheck className="mr-1 size-4" /> Mark all read
                        </Button>
                    </div>
                    <div className="space-y-3">
                        {notifications.data.map((n) => (
                            <Card
                                key={n.id}
                                className={`rounded-2xl ${!n.read_at ? 'border-indigo-300 bg-indigo-50/30 dark:bg-indigo-950/10' : ''}`}
                            >
                                <CardContent className="flex gap-4 p-5">
                                    <span
                                        className={`mt-1 size-2 shrink-0 rounded-full ${n.read_at ? 'bg-muted' : 'bg-indigo-500 ring-4 ring-indigo-500/10'}`}
                                    />
                                    <div className="flex-1">
                                        <div className="flex items-start justify-between gap-3">
                                            <h2 className="font-semibold">
                                                {n.title}
                                            </h2>
                                            <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                {new Date(
                                                    n.created_at,
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {n.body}
                                        </p>
                                        <div className="mt-3 flex gap-2">
                                            {n.data.document_id && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        router.patch(
                                                            `/notifications/${n.id}/read`,
                                                            {},
                                                            {
                                                                onSuccess: () =>
                                                                    router.visit(
                                                                        `/archive/${n.data.document_id}`,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Open document
                                                </Button>
                                            )}
                                            {!n.read_at && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/notifications/${n.id}/read`,
                                                        )
                                                    }
                                                >
                                                    Mark read
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                        {!notifications.data.length && (
                            <div className="rounded-2xl border border-dashed p-12 text-center text-sm text-muted-foreground">
                                You are all caught up.
                            </div>
                        )}
                    </div>
                </main>
                <aside>
                    <Card className="sticky top-6 rounded-2xl">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Delivery preferences
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                <fieldset>
                                    <legend className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Sources
                                    </legend>
                                    {[
                                        ['source_rbi', 'RBI'],
                                        ['source_income_tax', 'Income Tax'],
                                        ['source_gst', 'GST'],
                                    ].map(([key, label]) => (
                                        <div
                                            key={key}
                                            className="space-y-2 border-b py-3 text-sm"
                                        >
                                            <label className="flex items-center justify-between">
                                                <span>{label}</span>
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        form.data[
                                                            key as keyof Prefs
                                                        ] as boolean
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            key as keyof Prefs,
                                                            e.target
                                                                .checked as never,
                                                        )
                                                    }
                                                    className="size-4 accent-indigo-600"
                                                />
                                            </label>
                                            <select
                                                value={
                                                    form.data[
                                                        `${key}_cadence` as keyof Prefs
                                                    ] as string
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        `${key}_cadence` as keyof Prefs,
                                                        e.target.value as never,
                                                    )
                                                }
                                                className="h-8 w-full rounded-md border bg-background px-2 text-xs"
                                            >
                                                <option value="immediate">
                                                    Immediate email
                                                </option>
                                                <option value="daily_digest">
                                                    Daily digest
                                                </option>
                                                <option value="weekly_digest">
                                                    Weekly digest
                                                </option>
                                            </select>
                                        </div>
                                    ))}
                                </fieldset>
                                <fieldset>
                                    <legend className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Channels
                                    </legend>
                                    <p className="flex items-center gap-2 py-2 text-sm">
                                        <Radio className="size-4" /> In-app ·
                                        always real-time
                                    </p>
                                    <label className="flex items-center gap-2 py-2 text-sm">
                                        <Mail className="size-4" />
                                        <input
                                            type="checkbox"
                                            checked={form.data.email_enabled}
                                            onChange={(e) =>
                                                form.setData(
                                                    'email_enabled',
                                                    e.target.checked,
                                                )
                                            }
                                        />{' '}
                                        Email
                                    </label>
                                </fieldset>
                                <Button
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    Save preferences
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </>
    );
}
Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: '/notifications' }],
};
