<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_reports', function (Blueprint $table): void {
            $table->foreignId('document_version_id')->nullable()->after('interpretation_id')->constrained()->nullOnDelete();
            $table->string('locale', 5)->default('en')->after('document_version_id');
            $table->string('category')->nullable()->default(null)->change();
        });

        DB::table('issue_reports')->orderBy('id')->eachById(function (object $issue): void {
            $versionId = DB::table('interpretations')->where('id', $issue->interpretation_id)->value('document_version_id');
            DB::table('issue_reports')->where('id', $issue->id)->update(['document_version_id' => $versionId]);
        });
    }

    public function down(): void
    {
        Schema::table('issue_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_version_id');
            $table->dropColumn('locale');
            $table->string('category')->default('other')->nullable(false)->change();
        });
    }
};
