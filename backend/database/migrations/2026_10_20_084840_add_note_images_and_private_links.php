<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('map_pin_id')->nullable()->constrained('map_pins')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::create('note_media', function (Blueprint $table) {
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->primary(['note_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_media');
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropConstrainedForeignId('map_pin_id');
            $table->dropColumn('revision');
        });
    }
};
