<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fencer_id')->constrained()->cascadeOnDelete();
            $table->string('type');                 // rating | qualify | standing | develop
            $table->string('weapon')->nullable();   // foil | epee | saber (null = primary / n/a)
            $table->json('params')->nullable();     // type-specific: target_rating, target, category, target_events
            $table->string('status')->default('active'); // active | achieved | dropped
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['fencer_id', 'status']);
        });

        // Backfill from the old single-enum goal column.
        foreach (DB::table('fencers')->whereNotNull('goal')->get(['id', 'goal', 'weapon']) as $f) {
            $goal = match ($f->goal) {
                'earn_b' => ['type' => 'rating', 'weapon' => $f->weapon, 'params' => ['target_rating' => 'B']],
                'qualify_jo' => ['type' => 'qualify', 'weapon' => $f->weapon, 'params' => ['target' => 'jo']],
                'regional_standing' => ['type' => 'standing', 'weapon' => $f->weapon, 'params' => ['category' => null]],
                'explore' => ['type' => 'develop', 'weapon' => null, 'params' => ['target_events' => 8]],
                default => null,
            };
            if ($goal) {
                DB::table('goals')->insert([
                    'fencer_id' => $f->id,
                    'type' => $goal['type'],
                    'weapon' => $goal['weapon'],
                    'params' => json_encode($goal['params']),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('fencers', function (Blueprint $table) {
            $table->dropColumn('goal');
        });
    }

    public function down(): void
    {
        Schema::table('fencers', function (Blueprint $table) {
            $table->string('goal')->nullable();
        });
        Schema::dropIfExists('goals');
    }
};
