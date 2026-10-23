<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_keys', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20);
            $t->string('source', 10)->default('admin');
            $t->string('label', 60)->nullable();
            $t->text('secret')->nullable();
            $t->string('fingerprint', 64);
            $t->string('last_four', 8);
            $t->string('status', 20)->default('active');
            $t->timestamp('resting_until')->nullable();
            $t->string('last_error', 160)->nullable();
            $t->unsignedInteger('uses')->default(0);
            $t->unsignedInteger('limit_hits')->default(0);
            $t->unsignedInteger('quota_remaining')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'fingerprint']);
            $t->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_keys');
    }
};
