<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fencer_id')->constrained()->cascadeOnDelete();
            // Optional link to a catalog tournament; results can also be free-form
            // (camps, unlisted locals, last season's events).
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name');                       // "Junior Women's Foil"
            $table->string('category')->nullable();             // JNR / CDT / D1A / ...
            $table->string('weapon');                           // foil | epee | sabre
            $table->date('fenced_on');
            $table->unsignedSmallInteger('place');
            $table->unsignedSmallInteger('field_size')->nullable();
            $table->string('rating_earned')->nullable();        // e.g. "C26"
            $table->decimal('points', 8, 2)->nullable();        // regional/national points
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fencer_id', 'fenced_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
