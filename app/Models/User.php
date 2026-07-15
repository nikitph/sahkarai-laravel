<?php

namespace App\Models;

use App\Enums\SupportedLocale;
use App\Enums\Tier;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $current_organization_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property UserRole $role
 * @property Tier $tier
 * @property SupportedLocale $locale
 * @property int $credits_balance
 * @property Carbon|null $credits_reset_at
 * @property Carbon|null $hard_delete_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $currentOrganization
 */
#[Fillable(['name', 'email', 'password', 'current_organization_id', 'role', 'tier', 'locale', 'credits_balance', 'credits_reset_at', 'hard_delete_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected $attributes = [
        'role' => 'individual_member',
        'tier' => 'free',
        'locale' => 'en',
        'credits_balance' => 0,
    ];

    public function isAdmin(): bool
    {
        return $this->role === UserRole::SaasAdmin;
    }

    public function canUseInterpretations(): bool
    {
        return $this->tier->canViewInterpretations();
    }

    public function canUseChat(): bool
    {
        return $this->tier->canChat();
    }

    public function preferredLocale(): string
    {
        return $this->locale->value;
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** @return HasOne<NotificationPreference, $this> */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /** @return HasMany<Chat, $this> */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /** @return HasMany<CreditLedger, $this> */
    public function creditLedger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    /** @return HasMany<ProductNotification, $this> */
    public function productNotifications(): HasMany
    {
        return $this->hasMany(ProductNotification::class);
    }

    /** @return HasMany<DocumentView, $this> */
    public function documentViews(): HasMany
    {
        return $this->hasMany(DocumentView::class);
    }

    /** @return BelongsToMany<Organization, $this, Membership, 'pivot'> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /** @return BelongsTo<Organization, $this> */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function roleFor(Organization $organization): ?Role
    {
        $membership = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $this->getKey())
            ->first();

        if (! $membership) {
            return null;
        }

        return $membership->role;
    }

    public function hasPermission(Permission $permission, Organization $organization): bool
    {
        return $this->roleFor($organization)?->allows($permission) ?? false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'tier' => Tier::class,
            'locale' => SupportedLocale::class,
            'credits_reset_at' => 'datetime',
            'hard_delete_at' => 'datetime',
        ];
    }
}
