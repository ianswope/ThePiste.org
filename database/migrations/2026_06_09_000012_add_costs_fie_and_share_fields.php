<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimal travel planning: an estimated cost per planned event.
        Schema::table('plan_items', function (Blueprint $table) {
            $table->decimal('est_cost', 8, 2)->nullable()->after('status');
        });

        // FIE / international events.
        Schema::table('tournaments', function (Blueprint $table) {
            // regional | national | fie_cadet | fie_junior | fie_senior
            $table->string('level')->default('regional')->after('is_nac');
            $table->char('country', 2)->default('US')->after('state');
        });

        // Opt-in: families chasing international points see FIE events.
        Schema::table('fencers', function (Blueprint $table) {
            $table->boolean('include_fie')->default(false)->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $t) => $t->dropColumn('est_cost'));
        Schema::table('tournaments', fn (Blueprint $t) => $t->dropColumn(['level', 'country']));
        Schema::table('fencers', fn (Blueprint $t) => $t->dropColumn('include_fie'));
    }
};
