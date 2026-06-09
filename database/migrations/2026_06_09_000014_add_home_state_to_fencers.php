<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fencers', function (Blueprint $table) {
            // Set from the ZIP geocode; lets region() work without a home club.
            $table->char('home_state', 2)->nullable()->after('home_zip');
        });
    }

    public function down(): void
    {
        Schema::table('fencers', fn (Blueprint $t) => $t->dropColumn('home_state'));
    }
};
