import { Head, router } from '@inertiajs/react';
import {
    Bot,
    Check,
    Crown,
    FileText,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/lib/i18n';

type Subscription = {
    tier: string;
    status: string;
    pending_tier: string | null;
    current_period_end: string | null;
    cancel_at: string | null;
    provider_subscription_id: string | null;
};
export default function Billing({
    subscription,
    plans,
}: {
    subscription: Subscription;
    plans: Record<string, { monthly_price: number; monthly_credits: number }>;
}) {
    const t = useT();
    const tiers = [
        {
            id: 'free',
            name: 'Free',
            icon: FileText,
            description: 'The original regulatory archive.',
            features: [
                'Browse and search the archive',
                'Download original publications',
            ],
        },
        {
            id: 'tier_1',
            name: 'Insight',
            icon: Sparkles,
            description: 'Understand and monitor every update.',
            features: [
                'Everything in Free',
                'Four-language interpretations',
                'Markdown & PDF exports',
                'In-app and email alerts',
            ],
        },
        {
            id: 'tier_2',
            name: 'Intelligence',
            icon: Bot,
            description: 'Explore each publication with grounded AI.',
            features: [
                'Everything in Insight',
                'Private document-scoped AI chat',
                `${plans.tier_2.monthly_credits} chat credits each cycle`,
                'JSON, Markdown & PDF chat exports',
            ],
        },
    ];

    return (
        <>
            <Head title={t('billing')} />
            <div className="mx-auto w-full max-w-7xl p-4 md:p-8">
                <div className="mx-auto mb-9 max-w-2xl text-center">
                    <Badge variant="secondary" className="mb-3">
                        <Crown className="mr-1 size-3" /> Simple, transparent
                        plans
                    </Badge>
                    <h1 className="text-3xl font-semibold md:text-4xl">
                        Choose how deeply you want to understand
                    </h1>
                    <p className="mt-3 text-muted-foreground">
                        Your archive remains available on every plan. Upgrade
                        when interpretations or document-grounded AI become
                        valuable.
                    </p>
                </div>
                {subscription.cancel_at && (
                    <div className="mx-auto mb-6 max-w-3xl rounded-xl border border-amber-300 bg-amber-50 p-4 text-center text-sm dark:bg-amber-950/20">
                        Your plan is scheduled to change to{' '}
                        {subscription.pending_tier?.replace('_', ' ')} on{' '}
                        {new Date(subscription.cancel_at).toLocaleDateString()}.
                        <Button
                            variant="link"
                            size="sm"
                            onClick={() => router.post('/billing/resume')}
                        >
                            Keep current plan
                        </Button>
                    </div>
                )}
                <div className="grid gap-5 lg:grid-cols-3">
                    {tiers.map((tier) => {
                        const current = subscription.tier === tier.id;
                        const price = plans[tier.id].monthly_price / 100;

                        return (
                            <Card
                                key={tier.id}
                                className={`relative rounded-3xl ${tier.id === 'tier_2' ? 'border-indigo-400 shadow-xl shadow-indigo-500/10' : 'border-border/60'}`}
                            >
                                {tier.id === 'tier_2' && (
                                    <Badge className="absolute -top-3 left-1/2 -translate-x-1/2">
                                        Most capable
                                    </Badge>
                                )}
                                <CardHeader>
                                    <span
                                        className={`mb-3 grid size-11 place-items-center rounded-xl ${tier.id === 'tier_2' ? 'bg-indigo-600 text-white' : 'bg-muted'}`}
                                    >
                                        <tier.icon className="size-5" />
                                    </span>
                                    <CardTitle className="text-xl">
                                        {tier.name}
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {tier.description}
                                    </p>
                                    <p className="pt-3">
                                        <span className="text-3xl font-semibold">
                                            ₹{price.toLocaleString('en-IN')}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {' '}
                                            / month
                                        </span>
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    <ul className="mb-7 space-y-3">
                                        {tier.features.map((f) => (
                                            <li
                                                key={f}
                                                className="flex gap-2 text-sm"
                                            >
                                                <Check className="size-4 shrink-0 text-emerald-600" />{' '}
                                                {f}
                                            </li>
                                        ))}
                                    </ul>
                                    {current ? (
                                        <Button
                                            className="w-full"
                                            variant="outline"
                                            disabled
                                        >
                                            <ShieldCheck className="mr-1 size-4" />{' '}
                                            Current plan
                                        </Button>
                                    ) : tier.id === 'free' ? (
                                        <Button
                                            className="w-full"
                                            variant="outline"
                                            onClick={() =>
                                                subscription.provider_subscription_id &&
                                                router.post('/billing/cancel')
                                            }
                                        >
                                            Cancel paid plan
                                        </Button>
                                    ) : (
                                        <Button
                                            className="w-full"
                                            onClick={() =>
                                                router.post(
                                                    '/billing/subscribe',
                                                    { tier: tier.id },
                                                )
                                            }
                                        >
                                            {subscription.tier === 'free'
                                                ? 'Choose'
                                                : 'Change to'}{' '}
                                            {tier.name}
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
                <p className="mt-8 text-center text-xs text-muted-foreground">
                    Payments and subscription lifecycle are handled by Razorpay.
                    Prices include product access; applicable taxes may be
                    added.
                </p>
            </div>
        </>
    );
}
Billing.layout = {
    breadcrumbs: [{ title: 'Plans & billing', href: '/billing' }],
};
