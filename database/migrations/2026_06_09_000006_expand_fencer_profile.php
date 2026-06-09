<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fencers', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('name');        // men | women | mixed
            $table->string('handedness')->nullable()->after('gender');  // right | left
            $table->unsignedSmallInteger('birth_year')->nullable()->after('handedness');
            $table->string('usa_fencing_id')->nullable()->unique()->after('birth_year');
        });

        // Ratings are per-weapon, so a fencer has one row per weapon they compete.
        Schema::create('fencer_weapons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fencer_id')->constrained()->cascadeOnDelete();
            $table->string('weapon');               // foil | epee | sabre
            $table->string('rating')->default('U'); // U | E | D | C | B | A (+ year, e.g. C26)
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['fencer_id', 'weapon']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fencer_weapons');
        Schema::table('fencers', function (Blueprint $table) {
            $table->dropColumn(['gender', 'handedness', 'birth_year', 'usa_fencing_id']);
        });
    }
};
