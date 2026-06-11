<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily digest commands scan on these columns:
     *  - notify-new-events:        tournaments WHERE alerted_at IS NULL
     *  - send-registration-reminders: plan_items WHERE reminded_at IS NULL AND status = 'planned'
     * Index them so the nightly cron stays a lookup, not a table scan, as the
     * catalog and plans grow.
     */
    public function up(): void
    {
        Schema::table('tournaments', fn (Blueprint $table) => $table->index('alerted_at'));
        Schema::table('plan_items', fn (Blueprint $table) => $table->index(['reminded_at', 'status']));
    }

    public function down(): void
    {
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropIndex(['alerted_at']));
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropIndex(['reminded_at', 'status']));
    }
};
