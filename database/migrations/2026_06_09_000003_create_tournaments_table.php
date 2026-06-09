<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('region')->nullable();          // "R2", "R3", ... or "NATIONAL"
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->boolean('is_nac')->default(false);
            $table->json('circuits')->nullable();           // ["RJCC","ROC","RYC"]
            $table->json('contested_events')->nullable();   // ["JNR","CDT","D1A","DV2"]
            $table->text('curated_note')->nullable();       // overrides generated note for marquee events
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index(['season_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
