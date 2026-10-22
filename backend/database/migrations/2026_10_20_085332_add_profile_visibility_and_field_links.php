<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_visibility', 16)->default('everyone');
            $table->string('connections_visibility', 16)->default('everyone');
            $table->string('activity_visibility', 16)->default('everyone');
        });
        Schema::table('map_pins', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('observations', function (Blueprint $table) {
            $table->foreignId('map_pin_id')->nullable()->constrained('map_pins')->nullOnDelete();
        });
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('plant_record_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stories', fn (Blueprint $table) => $table->dropConstrainedForeignId('plant_record_id'));
        Schema::table('observations', fn (Blueprint $table) => $table->dropConstrainedForeignId('map_pin_id'));
        Schema::table('map_pins', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['profile_visibility', 'connections_visibility', 'activity_visibility']));
    }
};
