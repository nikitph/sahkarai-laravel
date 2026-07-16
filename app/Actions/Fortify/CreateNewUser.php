<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportedLocale;
use App\Enums\Tier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'locale' => ['nullable', 'string', 'in:en,hi,gu,mr'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
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
    }
}
