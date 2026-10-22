<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->foreignId('observation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('map_pin_id')->nullable()->constrained('map_pins')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('observation_id');
            $table->dropConstrainedForeignId('map_pin_id');
        });
    }
};
