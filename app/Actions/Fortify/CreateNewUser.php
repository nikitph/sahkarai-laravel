<?php

namespace App\Actions\Fortify;

use App\Actions\Billing\PurchaseOrganizationSubscription;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportedLocale;
use App\Enums\Tier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly PurchaseOrganizationSubscription $purchaseOrganizationSubscription) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $organizationBillingEnabled = (bool) config('sahkarai.razorpay.organization_billing.enabled');
        $accountTypes = $organizationBillingEnabled ? ['individual', 'organization'] : ['individual'];

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'locale' => ['nullable', 'string', 'in:en,hi,gu,mr'],
            'account_type' => ['nullable', Rule::in($accountTypes)],
            'organization_name' => ['required_if:account_type,organization', 'nullable', 'string', 'max:120'],
            'organization_tier' => [
                'required_if:account_type,organization',
                'nullable',
                Rule::enum(Tier::class),
                Rule::notIn([Tier::Free->value]),
            ],
            'organization_seats' => [
                'required_if:account_type,organization',
                'nullable',
                'integer',
                'min:'.config('sahkarai.razorpay.organization_billing.min_seats', 2),
                'max:'.config('sahkarai.razorpay.organization_billing.max_seats', 25),
            ],
        ])->validate();

        $user = DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'locale' => $input['locale'] ?? SupportedLocale::English->value,
            ]);

            if (config('sahkarai.auth.auto_verify_email')) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->subscription()->create([
                'tier' => Tier::Free,
                'status' => SubscriptionStatus::Free,
            ]);
            $user->notificationPreference()->create();

            return $user->refresh();
        });

        if (($input['account_type'] ?? 'individual') === 'organization') {
            $this->purchaseOrganizationSubscription->handle(
                $user,
                (string) $input['organization_name'],
                Tier::from((string) $input['organization_tier']),
                (int) $input['organization_seats'],
            );
        }

        return $user->refresh();
    }
}
