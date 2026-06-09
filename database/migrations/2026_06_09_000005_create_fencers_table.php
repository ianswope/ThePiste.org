<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fencers', function (Blueprint $table) {
            $table->id();
            // managing account (parent or the fencer themselves); null = anonymous/unclaimed
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('home_club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('name');
            $table->string('weapon');                       // foil | epee | sabre
            $table->string('age_group');                    // Y10,Y12,Y14,Cadet,Junior,Senior,Vet
            $table->string('rating')->default('U');         // U,E,D,C,B,A
            $table->string('home_zip')->nullable();
            $table->decimal('home_lat', 9, 6)->nullable();
            $table->decimal('home_lng', 9, 6)->nullable();
            $table->string('goal')->nullable();             // earn_b | qualify_jo | regional_standing | explore
            $table->unsignedInteger('drive_radius_miles')->default(450);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fencers');
    }
};
