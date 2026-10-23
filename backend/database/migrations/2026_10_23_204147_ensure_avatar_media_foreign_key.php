<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! collect(Schema::getForeignKeys('users'))->contains(fn ($key) => $key['columns'] === ['avatar_media_id'])) {
            Schema::table('users', fn (Blueprint $table) => $table->foreign('avatar_media_id')->references('id')->on('media')->nullOnDelete());
        }
    }

    public function down(): void {}
};
