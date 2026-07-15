<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_views', function (Blueprint $table): void {
            $table->foreignId('document_version_id')->nullable()->after('regulatory_document_id')->constrained()->cascadeOnDelete();
        });

        DB::table('document_views')->orderBy('id')->eachById(function (object $view): void {
            $versionId = DB::table('document_versions')
                ->where('regulatory_document_id', $view->regulatory_document_id)
                ->orderByDesc('version')
                ->value('id');
            DB::table('document_views')->where('id', $view->id)->update(['document_version_id' => $versionId]);
        });

        Schema::table('document_views', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'regulatory_document_id']);
            $table->unique(['user_id', 'document_version_id']);
        });
    }

    public function down(): void
    {
        DB::table('document_views')
            ->whereNotNull('document_version_id')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (object $view) => $view->user_id.':'.$view->regulatory_document_id)
            ->each(function (Collection $views): void {
                DB::table('document_views')->whereIn('id', $views->skip(1)->pluck('id'))->delete();
            });

        Schema::table('document_views', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'document_version_id']);
            $table->unique(['user_id', 'regulatory_document_id']);
            $table->dropConstrainedForeignId('document_version_id');
        });
    }
};
