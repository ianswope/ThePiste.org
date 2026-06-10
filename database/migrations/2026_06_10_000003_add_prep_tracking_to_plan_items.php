<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            // Per-event prep pipeline, parallel to status/paid. "na" = not needed
            // (a driveable day trip needs no lodging); "pending" = still to do.
            $table->string('travel_status', 10)->default('pending')->after('paid');   // pending | booked | na
            $table->string('lodging_status', 10)->default('pending')->after('travel_status'); // pending | booked | na
            $table->string('coaching_status', 10)->default('undecided')->after('lodging_status'); // undecided | arranged | none
        });
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn(['travel_status', 'lodging_status', 'coaching_status']));
    }
};
