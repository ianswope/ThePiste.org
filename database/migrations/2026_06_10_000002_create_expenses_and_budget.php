<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_plans', function (Blueprint $table) {
            // The yellow input cell from the spreadsheet era: season spending target.
            $table->decimal('budget', 8, 2)->nullable()->after('share_slug');
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->string('paid', 10)->default('no')->after('status'); // no | partial | yes
        });

        // Per-category trip costs. One row per (item, category); estimate is set
        // while planning, actual replaces it as bookings land. Categories come
        // from config/fencing.php expense_categories.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->decimal('est_amount', 8, 2)->nullable();
            $table->decimal('actual_amount', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['plan_item_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn('paid'));
        Schema::table('season_plans', fn (Blueprint $table) => $table->dropColumn('budget'));
    }
};
