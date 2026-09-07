import { Form, Head } from '@inertiajs/react';
import { Building2, Check, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Plan = { monthly_price: number; monthly_credits: number };
type Discount = { min: number; max: number; percent: number };
type OrganizationBilling = {
    enabled: boolean;
    autoApprove: boolean;
    minSeats: number;
    maxSeats: number;
    plans: Record<string, Plan>;
    discounts: Discount[];
};

type Props = {
    passwordRules: string;
    organizationBilling: OrganizationBilling;
};

const tierNames: Record<string, string> = {
    tier_1: 'Insight',
    tier_2: 'Intelligence',
    tier_3: 'Personalized',
};

export default function Register({
    passwordRules,
    organizationBilling,
}: Props) {
    const [accountType, setAccountType] = useState<
        'individual' | 'organization'
    >('individual');
    const [tier, setTier] = useState(
        organizationBilling.plans.tier_2 ? 'tier_2' : 'tier_1',
    );
    const [seats, setSeats] = useState(
        Math.min(
            organizationBilling.maxSeats,
            Math.max(organizationBilling.minSeats, 5),
        ),
    );
    const discount =
        organizationBilling.discounts.find(
            (band) => seats >= band.min && seats <= band.max,
        )?.percent ?? 0;
    const quote = useMemo(() => {
        const subtotal =
            (organizationBilling.plans[tier]?.monthly_price ?? 0) * seats;

        return {
            subtotal,
            total: Math.floor(subtotal * (1 - discount / 100)),
        };
    }, [discount, organizationBilling.plans, seats, tier]);

    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-7"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="account_type"
                            value={accountType}
                        />

                        {organizationBilling.enabled && (
                            <div className="space-y-3">
                                <Label>What are you setting up?</Label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        aria-pressed={
                                            accountType === 'individual'
                                        }
                                        onClick={() =>
                                            setAccountType('individual')
                                        }
                                        className={`rounded-xl border p-4 text-left transition ${accountType === 'individual' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border/70 hover:bg-muted/40'}`}
                                    >
                                        <UserRound className="mb-3 size-5" />
                                        <p className="font-semibold">
                                            Just for me
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Start free and choose an individual
                                            plan later.
                                        </p>
                                    </button>
                                    <button
                                        type="button"
                                        aria-pressed={
                                            accountType === 'organization'
                                        }
                                        onClick={() =>
                                            setAccountType('organization')
                                        }
                                        className={`rounded-xl border p-4 text-left transition ${accountType === 'organization' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border/70 hover:bg-muted/40'}`}
                                    >
                                        <Building2 className="mb-3 size-5" />
                                        <p className="font-semibold">
                                            My organization
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Select a tier and buy{' '}
                                            {organizationBilling.minSeats}–
                                            {organizationBilling.maxSeats} seats
                                            now.
                                        </p>
                                    </button>
                                </div>
                                <InputError message={errors.account_type} />
                            </div>
                        )}

                        <div
                            className={`grid gap-8 ${accountType === 'organization' ? 'lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)]' : ''}`}
                        >
                            <div
                                className={`grid content-start gap-5 ${accountType === 'individual' ? 'mx-auto w-full max-w-sm' : ''}`}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Your name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        name="name"
                                        placeholder="Full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        tabIndex={2}
                                        autoComplete="email"
                                        name="email"
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="locale">
                                        Preferred language
                                    </Label>
                                    <select
                                        id="locale"
                                        name="locale"
                                        defaultValue="en"
                                        tabIndex={3}
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="en">English</option>
                                        <option value="hi">हिन्दी</option>
                                        <option value="gu">ગુજરાતી</option>
                                        <option value="mr">मराठी</option>
                                    </select>
                                    <InputError message={errors.locale} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        required
                                        tabIndex={4}
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="Password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        tabIndex={5}
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        placeholder="Confirm password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>
                            </div>

                            {accountType === 'organization' && (
                                <div className="space-y-5 rounded-2xl border p-5">
                                    <div>
                                        <h2 className="text-lg font-semibold">
                                            Organization plan
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            You will be the owner and use the
                                            first seat.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="organization_name">
                                            Organization name
                                        </Label>
                                        <Input
                                            id="organization_name"
                                            name="organization_name"
                                            required
                                            placeholder="Acme Cooperative"
                                        />
                                        <InputError
                                            message={errors.organization_name}
                                        />
                                    </div>

                                    <input
                                        type="hidden"
                                        name="organization_tier"
                                        value={tier}
                                    />
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {Object.entries(
                                            organizationBilling.plans,
                                        ).map(([id, plan]) => (
                                            <button
                                                type="button"
                                                key={id}
                                                aria-pressed={tier === id}
                                                onClick={() => setTier(id)}
                                                className={`rounded-xl border p-3 text-left transition ${tier === id ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border/70 hover:bg-muted/40'}`}
                                            >
                                                <p className="font-semibold">
                                                    {tierNames[id]}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    ₹
                                                    {(
                                                        plan.monthly_price / 100
                                                    ).toLocaleString(
                                                        'en-IN',
                                                    )}{' '}
                                                    / seat
                                                </p>
                                                {plan.monthly_credits > 0 && (
                                                    <p className="mt-2 flex items-center gap-1 text-xs">
                                                        <Check className="size-3 text-emerald-600" />
                                                        {plan.monthly_credits}{' '}
                                                        credits
                                                    </p>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                    <InputError
                                        message={errors.organization_tier}
                                    />

                                    <div className="space-y-3 rounded-xl border p-4">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="organization_seats">
                                                Seats
                                            </Label>
                                            <span className="text-xl font-semibold">
                                                {seats}
                                            </span>
                                        </div>
                                        <input
                                            id="organization_seats"
                                            name="organization_seats"
                                            type="range"
                                            min={organizationBilling.minSeats}
                                            max={organizationBilling.maxSeats}
                                            value={seats}
                                            onChange={(event) =>
                                                setSeats(
                                                    Number(event.target.value),
                                                )
                                            }
                                            className="w-full accent-primary"
                                        />
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>
                                                {organizationBilling.minSeats}{' '}
                                                seats
                                            </span>
                                            <span>
                                                {organizationBilling.maxSeats}{' '}
                                                seats
                                            </span>
                                        </div>
                                        <InputError
                                            message={errors.organization_seats}
                                        />
                                    </div>

                                    <div className="space-y-2 rounded-xl bg-muted/40 p-4">
                                        <div className="flex justify-between text-sm">
                                            <span>{seats} seats</span>
                                            <span>
                                                ₹
                                                {(
                                                    quote.subtotal / 100
                                                ).toLocaleString('en-IN')}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-sm text-emerald-700">
                                            <span>Bulk discount</span>
                                            <span>−{discount}%</span>
                                        </div>
                                        <div className="border-t pt-3 text-2xl font-semibold">
                                            ₹
                                            {(quote.total / 100).toLocaleString(
                                                'en-IN',
                                            )}
                                            <span className="text-xs font-normal text-muted-foreground">
                                                {' '}
                                                / month
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {organizationBilling.autoApprove
                                                ? 'Payment confirmation is temporarily bypassed; the plan activates when the account is created.'
                                                : 'You will continue to secure checkout after creating your account.'}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            tabIndex={6}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            {accountType === 'organization'
                                ? organizationBilling.autoApprove
                                    ? 'Create account and activate plan'
                                    : 'Create account and continue to checkout'
                                : 'Create account'}
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={7}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description:
        'Start individually or set up an organization plan for your team.',
    wide: true,
};
