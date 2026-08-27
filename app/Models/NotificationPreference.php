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
 * @property bool $source_cbic
 * @property bool $source_nabard
 * @property NotificationCadence $source_rbi_cadence
 * @property NotificationCadence $source_income_tax_cadence
 * @property NotificationCadence $source_gst_cadence
 * @property NotificationCadence $source_cbic_cadence
 * @property NotificationCadence $source_nabard_cadence
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
            'source_cbic' => 'boolean',
            'source_nabard' => 'boolean',
            'source_rbi_cadence' => NotificationCadence::class,
            'source_income_tax_cadence' => NotificationCadence::class,
            'source_gst_cadence' => NotificationCadence::class,
            'source_cbic_cadence' => NotificationCadence::class,
            'source_nabard_cadence' => NotificationCadence::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
