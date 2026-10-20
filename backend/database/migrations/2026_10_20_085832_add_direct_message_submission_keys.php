<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->uuid('client_message_id')->nullable();
            $table->unique(['thread_id', 'user_id', 'client_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropUnique(['thread_id', 'user_id', 'client_message_id']);
            $table->dropColumn('client_message_id');
        });
    }
};
