<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_sync_cursors')) {
            Schema::create('analytics_sync_cursors', function (Blueprint $table): void {
                $table->id();
                $table->string('source_key', 64);
                $table->string('stream', 32);
                $table->text('cursor')->nullable();
                $table->unsignedInteger('last_count')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['source_key', 'stream']);
            });
        }

        Schema::table('view_logs', function (Blueprint $table): void {
            $table->string('site_key', 64)->nullable()->index();
            $table->string('source_event_id', 128)->nullable();
            $table->string('visitor_id', 128)->nullable()->index();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('device_type', 32)->nullable()->index();
            $table->string('source_type', 64)->nullable()->index();
            $table->string('page_title', 512)->nullable();
            $table->unique(['site_key', 'source_event_id']);
        });

        Schema::table('lead_submissions', function (Blueprint $table): void {
            $table->string('source_system', 64)->nullable()->index();
            $table->string('external_id', 128)->nullable();
            $table->timestamp('external_updated_at')->nullable()->index();
            $table->unique(['source_system', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table): void {
            $table->dropUnique(['source_system', 'external_id']);
            $table->dropColumn(['source_system', 'external_id', 'external_updated_at']);
        });
        Schema::table('view_logs', function (Blueprint $table): void {
            $table->dropUnique(['site_key', 'source_event_id']);
            $table->dropColumn(['site_key', 'source_event_id', 'visitor_id', 'session_id', 'device_type', 'source_type', 'page_title']);
        });
        Schema::dropIfExists('analytics_sync_cursors');
    }
};
