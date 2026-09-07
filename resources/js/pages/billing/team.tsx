import { Head, router } from '@inertiajs/react';
import { Building2, Check, UsersRound } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Plan = { monthly_price: number; monthly_credits: number };
type Discount = { min: number; max: number; percent: number };
type Checkout = {
    key: string;
    subscription_id: string;
    tier: string;
    seats: number;
    name: string;
    email: string;
} | null;

declare global {
    interface Window {
        Razorpay?: new (options: Record<string, unknown>) => {
            open: () => void;
            on: (event: string, callback: () => void) => void;
        };
    }
}

const tierNames: Record<string, string> = {
    tier_1: 'Insight',
    tier_2: 'Intelligence',
    tier_3: 'Personalized',
};

export default function TeamBilling({
    billingOrganization,
    canManageSeats,
    subscription,
    plans,
    discounts,
    checkout,
    autoApprove,
}: {
    billingOrganization: { id: number; name: string } | null;
    canManageSeats: boolean;
    subscription: {
        tier: string;
        pending_tier: string | null;
        status: string;
        seat_quantity: number;
        discount_basis_points: number;
    } | null;
    plans: Record<string, Plan>;
    discounts: Discount[];
    checkout: Checkout;
    autoApprove: boolean;
}) {
    const [tier, setTier] = useState('tier_2');
    const [seats, setSeats] = useState(5);
    const [organizationName, setOrganizationName] = useState('');
    const discount =
        discounts.find((band) => seats >= band.min && seats <= band.max)
            ?.percent ?? 0;
    const quote = useMemo(() => {
        const subtotal = plans[tier].monthly_price * seats;

        return {
            subtotal,
            total: Math.floor(subtotal * (1 - discount / 100)),
        };
    }, [discount, plans, seats, tier]);

    useEffect(() => {
        if (!checkout) {
            return;
        }

        const open = () => {
            if (!window.Razorpay) {
                toast.error('Unable to load Razorpay Checkout. Please retry.');

                return;
            }

            const modal = new window.Razorpay({
                key: checkout.key,
                subscription_id: checkout.subscription_id,
                name: 'SahkarAI',
                description: `${checkout.seats} ${tierNames[checkout.tier]} seats`,
                prefill: { name: checkout.name, email: checkout.email },
                theme: { color: '#4f46e5' },
                handler: () =>
                    toast.success(
                        'Payment received. Seats activate after secure webhook confirmation.',
                    ),
            });
            modal.on('payment.failed', () =>
                toast.error('Payment was not completed.'),
            );
            modal.open();
        };

        const existing = document.querySelector<HTMLScriptElement>(
            'script[data-razorpay-checkout]',
        );

        if (existing) {
            if (window.Razorpay) {
                open();
            } else {
                existing.addEventListener('load', open, { once: true });
            }

            return;
        }

        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.async = true;
        script.dataset.razorpayCheckout = 'true';
        script.addEventListener('load', open, { once: true });
        document.head.appendChild(script);
    }, [checkout]);

    const submit = () =>
        router.post('/billing/team', {
            organization_name: billingOrganization
                ? undefined
                : organizationName,
            tier,
            seats,
        });

    return (
        <>
            <Head title="Team billing" />
            <div className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <div className="mb-8 max-w-2xl">
                    <Badge variant="secondary" className="mb-3">
                        <Building2 className="mr-1 size-3" /> Organization plans
                    </Badge>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        One plan for your whole team
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Buy 2–25 seats. The owner uses one seat, and every
                        active member receives the selected tier and its monthly
                        credits.
                    </p>
                </div>

                {subscription ? (
                    <Card className="max-w-2xl rounded-2xl">
                        <CardHeader>
                            <CardTitle>{billingOrganization?.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <p className="text-lg font-medium">
                                {subscription.seat_quantity} seats ·{' '}
                                {tierNames[
                                    subscription.pending_tier ??
                                        subscription.tier
                                ] ?? 'Pending'}
                            </p>
                            <p className="text-sm text-muted-foreground capitalize">
                                {subscription.status} ·{' '}
                                {subscription.discount_basis_points / 100}% bulk
                                discount
                            </p>
                            {canManageSeats && (
                                <Button
                                    className="mt-3"
                                    onClick={() => router.visit('/members')}
                                    disabled={subscription.status !== 'active'}
                                >
                                    <UsersRound className="mr-1 size-4" />{' '}
                                    Manage seats
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-[1.3fr_.7fr]">
                        <div className="space-y-5">
                            {!billingOrganization && (
                                <div className="space-y-2">
                                    <Label htmlFor="organization_name">
                                        Organization name
                                    </Label>
                                    <Input
                                        id="organization_name"
                                        value={organizationName}
                                        onChange={(event) =>
                                            setOrganizationName(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Acme Cooperative"
                                    />
                                </div>
                            )}
                            <div className="grid gap-3 md:grid-cols-3">
                                {Object.entries(plans).map(([id, plan]) => (
                                    <button
                                        type="button"
                                        key={id}
                                        onClick={() => setTier(id)}
                                        className={`rounded-2xl border p-4 text-left transition ${tier === id ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border/70 hover:bg-muted/40'}`}
                                    >
                                        <p className="font-semibold">
                                            {tierNames[id]}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            ₹
                                            {(
                                                plan.monthly_price / 100
                                            ).toLocaleString('en-IN')}{' '}
                                            / seat
                                        </p>
                                        {plan.monthly_credits > 0 && (
                                            <p className="mt-3 flex items-center gap-1 text-xs">
                                                <Check className="size-3 text-emerald-600" />
                                                {plan.monthly_credits} credits
                                                per seat
                                            </p>
                                        )}
                                    </button>
                                ))}
                            </div>
                            <div className="space-y-3 rounded-2xl border p-5">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="seats">Seats</Label>
                                    <span className="text-2xl font-semibold">
                                        {seats}
                                    </span>
                                </div>
                                <input
                                    id="seats"
                                    type="range"
                                    min={2}
                                    max={25}
                                    value={seats}
                                    onChange={(event) =>
                                        setSeats(Number(event.target.value))
                                    }
                                    className="w-full accent-primary"
                                />
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>2 seats · 5% off</span>
                                    <span>25 seats · 25% off</span>
                                </div>
                            </div>
                        </div>
                        <Card className="h-fit rounded-2xl">
                            <CardHeader>
                                <CardTitle>Monthly total</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-between text-sm">
                                    <span>{seats} seats</span>
                                    <span>
                                        ₹
                                        {(quote.subtotal / 100).toLocaleString(
                                            'en-IN',
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm text-emerald-700">
                                    <span>Bulk discount</span>
                                    <span>−{discount}%</span>
                                </div>
                                <div className="border-t pt-4 text-3xl font-semibold">
                                    ₹
                                    {(quote.total / 100).toLocaleString(
                                        'en-IN',
                                    )}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {' '}
                                        / month
                                    </span>
                                </div>
                                <Button
                                    className="w-full"
                                    onClick={submit}
                                    disabled={
                                        !billingOrganization &&
                                        !organizationName.trim()
                                    }
                                >
                                    {autoApprove
                                        ? 'Activate organization plan'
                                        : 'Continue to secure checkout'}
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    {autoApprove
                                        ? 'Payment confirmation is temporarily bypassed; access activates immediately.'
                                        : 'Taxes may be added by Razorpay. Access begins only after payment confirmation.'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

TeamBilling.layout = {
    breadcrumbs: [{ title: 'Team billing', href: '/billing/team' }],
};
