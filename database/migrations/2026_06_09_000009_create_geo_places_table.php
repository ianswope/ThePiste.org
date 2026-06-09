<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cache of geocoded "City, ST" lookups so CSV imports never re-hit the API.
        Schema::create('geo_places', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('state', 2);
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->timestamps();

            $table->unique(['city', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_places');
    }
};
