<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('response_style', 20)->default('friendly');
        });
        Schema::create('chat_message_media', function (Blueprint $table) {
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->primary(['chat_message_id', 'media_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_media');
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('response_style');
        });
    }
};
