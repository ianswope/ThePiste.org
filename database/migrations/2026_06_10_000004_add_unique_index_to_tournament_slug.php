<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The catalog upsert (TournamentImporter / AskFRED sync) keys on slug via
     * updateOrCreate, so the slug must be unique for re-imports to update in
     * place instead of inserting a duplicate. Every other slug column (clubs,
     * seasons, share_slug, external_id) is already unique; this one was missed.
     *
     * Refuse to add the constraint while duplicates exist rather than silently
     * collapsing rows (which would cascade-delete their plan_items / results).
     * Run `php artisan thepiste:dedupe-tournaments` first if this aborts.
     */
    public function up(): void
    {
        $dupes = DB::table('tournaments')
            ->select('slug', DB::raw('count(*) as n'))
            ->groupBy('slug')
            ->having('n', '>', 1)
            ->pluck('n', 'slug');

        if ($dupes->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add a unique index on tournaments.slug: duplicate slugs exist ('
                .$dupes->keys()->implode(', ').'). Run `php artisan thepiste:dedupe-tournaments` first.'
            );
        }

        Schema::table('tournaments', fn (Blueprint $table) => $table->unique('slug'));
    }

    public function down(): void
    {
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropUnique(['slug']));
    }
};
