<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table): void {
            $table->string('extraction_status')->default('pending')->after('status')->index();
            $table->string('interpretation_status')->default('pending')->after('extraction_status')->index();
        });

        DB::table('document_versions')->orderBy('id')->eachById(function (object $version): void {
            $extractionStatus = match ($version->status) {
                'extraction_failed' => 'failed',
                'extracted', 'published', 'interpretation_generating', 'interpretation_partial', 'interpretation_failed' => 'ok',
                default => 'pending',
            };
            $interpretationStatus = match ($version->status) {
                'published' => 'published',
                'interpretation_partial' => 'partial',
                'interpretation_failed' => 'failed',
                'interpretation_generating' => 'generating',
                default => 'pending',
            };
            DB::table('document_versions')->where('id', $version->id)->update([
                'extraction_status' => $extractionStatus,
                'interpretation_status' => $interpretationStatus,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table): void {
            $table->dropColumn(['extraction_status', 'interpretation_status']);
        });
    }
};
