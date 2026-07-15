import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    subscription,
    creditLedger,
}: {
    subscription: { current_period_end: string | null } | null;
    creditLedger: {
        id: number;
        amount: number;
        balance_after: number;
        reason: string;
        created_at: string;
    }[];
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="locale">
                                    Preferred language
                                </Label>
                                <select
                                    id="locale"
                                    name="locale"
                                    defaultValue={auth.user.locale}
                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="en">English</option>
                                    <option value="hi">हिन्दी</option>
                                    <option value="gu">ગુજરાતી</option>
                                    <option value="mr">मराठी</option>
                                </select>
                                <InputError message={errors.locale} />
                                <p className="text-xs text-muted-foreground">
                                    Static interface copy, emails and
                                    interpretations use this language when
                                    available.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Plan & usage"
                    description="Your current product access and billing cycle"
                />
                <Card className="rounded-2xl">
                    <CardContent className="grid gap-4 p-5 sm:grid-cols-3">
                        <div>
                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                Tier
                            </p>
                            <p className="mt-1 font-semibold capitalize">
                                {auth.user.tier.replace('_', ' ')}
                            </p>
                        </div>
                        {subscription?.current_period_end && (
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Next billing date
                                </p>
                                <p className="mt-1 font-semibold">
                                    {new Date(
                                        subscription.current_period_end,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                        )}
                        {auth.user.tier === 'tier_2' && (
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Credit balance
                                </p>
                                <p className="mt-1 font-semibold">
                                    {auth.user.credits_balance}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Button asChild variant="outline">
                    <Link href="/billing">Change plan</Link>
                </Button>
                {auth.user.tier === 'tier_2' && creditLedger.length > 0 && (
                    <Card className="rounded-2xl">
                        <CardContent className="p-5">
                            <p className="mb-3 text-sm font-semibold">
                                Recent credit activity
                            </p>
                            <div className="divide-y text-sm">
                                {creditLedger.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div>
                                            <p className="capitalize">
                                                {entry.reason.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(
                                                    entry.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p
                                                className={
                                                    entry.amount < 0
                                                        ? 'text-amber-700'
                                                        : 'text-emerald-700'
                                                }
                                            >
                                                {entry.amount > 0 ? '+' : ''}
                                                {entry.amount}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Balance {entry.balance_after}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
