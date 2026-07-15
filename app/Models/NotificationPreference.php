<?php

namespace App\Models;

use App\Enums\NotificationCadence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property bool $source_rbi
 * @property bool $source_income_tax
 * @property bool $source_gst
 * @property bool $in_app_enabled
 * @property bool $email_enabled
 * @property NotificationCadence $source_rbi_cadence
 * @property NotificationCadence $source_income_tax_cadence
 * @property NotificationCadence $source_gst_cadence
 */
class NotificationPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_rbi' => 'boolean',
            'source_income_tax' => 'boolean',
            'source_gst' => 'boolean',
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'source_rbi_cadence' => NotificationCadence::class,
            'source_income_tax_cadence' => NotificationCadence::class,
            'source_gst_cadence' => NotificationCadence::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
