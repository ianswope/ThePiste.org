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
            // Stable id at the source (AskFRED tournament UUID) so date changes
            // reconcile in place instead of duplicating, + a seen-stamp so the
            // audit sweep can flag events that vanished from the source.
            $table->string('external_id')->nullable()->unique()->after('slug');
            $table->timestamp('last_seen_at')->nullable()->after('source_url');
        });

        // Backfill from the source_url of already-synced rows.
        foreach (DB::table('tournaments')->where('source_url', 'like', '%askfred.net/tournaments/%')->get() as $t) {
            DB::table('tournaments')->where('id', $t->id)->update([
                'external_id' => basename(parse_url($t->source_url, PHP_URL_PATH)),
                'last_seen_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'last_seen_at']);
        });
    }
};
