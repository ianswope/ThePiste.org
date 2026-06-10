<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // When this event was included in a new-event alert digest.
            $table->timestamp('alerted_at')->nullable()->after('last_seen_at');
        });

        Schema::table('plan_items', function (Blueprint $table) {
            // When the owner was nudged that registration is approaching.
            $table->timestamp('reminded_at')->nullable()->after('notes');
        });

        // The existing catalog predates alerts — without this backfill the
        // first run would email every user the entire season.
        DB::table('tournaments')->update(['alerted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropColumn('alerted_at'));
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
    }
};
