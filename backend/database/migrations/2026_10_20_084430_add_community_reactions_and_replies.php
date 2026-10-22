<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->index(['post_id', 'parent_id', 'id']);
        });
        Schema::create('social_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            $table->string('reaction', 20);
            $table->timestamps();
            $table->unique(['user_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_reactions');
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'parent_id', 'id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
