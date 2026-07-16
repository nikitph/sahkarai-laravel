<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_deliveries')
            ->whereNotNull('product_notification_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'product_notification_id', 'user_id', 'channel'])
            ->groupBy(fn (object $delivery): string => implode(':', [
                $delivery->product_notification_id,
                $delivery->user_id,
                $delivery->channel,
            ]))
            ->each(function ($deliveries): void {
                $duplicateIds = $deliveries->pluck('id')->slice(1);
                if ($duplicateIds->isNotEmpty()) {
                    DB::table('notification_deliveries')->whereIn('id', $duplicateIds)->delete();
                }
            });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->unique(
                ['product_notification_id', 'user_id', 'channel'],
                'notification_deliveries_notification_user_channel_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropUnique('notification_deliveries_notification_user_channel_unique');
        });
    }
};
