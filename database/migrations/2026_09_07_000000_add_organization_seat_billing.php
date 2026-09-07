<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('organization_id')->nullable()->after('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('purchaser_user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('seat_quantity')->default(1)->after('pending_tier');
            $table->unsignedTinyInteger('pending_seat_quantity')->nullable()->after('seat_quantity');
            $table->unsignedSmallInteger('discount_basis_points')->default(0)->after('pending_seat_quantity');
            $table->unsignedSmallInteger('pending_discount_basis_points')->nullable()->after('discount_basis_points');
            $table->unsignedInteger('unit_price')->nullable()->after('pending_discount_basis_points');
            $table->string('provider_offer_id')->nullable()->after('provider_subscription_id');
            $table->string('pending_provider_offer_id')->nullable()->after('provider_offer_id');
        });

        Schema::create('organization_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'invitation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_seats');
        DB::table('subscriptions')->whereNotNull('organization_id')->delete();

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('purchaser_user_id');
            $table->dropColumn([
                'seat_quantity',
                'pending_seat_quantity',
                'discount_basis_points',
                'pending_discount_basis_points',
                'unit_price',
                'provider_offer_id',
                'pending_provider_offer_id',
            ]);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
