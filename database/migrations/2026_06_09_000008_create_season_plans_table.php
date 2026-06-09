<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fencer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('share_slug')->nullable()->unique();
            $table->timestamps();

            $table->unique(['fencer_id', 'season_id']);
        });

        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('planned'); // planned | registered | attended | skipped
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['season_plan_id', 'tournament_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
        Schema::dropIfExists('season_plans');
    }
};
