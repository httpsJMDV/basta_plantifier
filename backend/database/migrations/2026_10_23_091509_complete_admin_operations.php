<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', fn (Blueprint $t) => $t->timestamp('hidden_at')->nullable());
        Schema::create('account_warnings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason');
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamps();
        });
        Schema::create('report_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('from_status');
            $t->string('status');
            $t->string('action');
            $t->text('reason');
            $t->timestamps();
        });
        Schema::table('corrections', function (Blueprint $t) {
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_notes')->nullable();
        });
        Schema::table('ai_usage', function (Blueprint $t) {
            $t->string('provider', 30)->nullable()->index();
            $t->string('operation', 30)->nullable();
            $t->string('error_category', 40)->nullable();
            $t->index(['created_at', 'status']);
        });
        Schema::create('backup_records', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 30);
            $t->string('status', 30);
            $t->string('artifact_sha256', 64)->nullable();
            $t->unsignedBigInteger('artifact_bytes')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $t) => $t->dropColumn('hidden_at'));
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('report_decisions');
        Schema::dropIfExists('account_warnings');
        Schema::table('corrections', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['reviewed_at', 'review_notes']);
        });
        Schema::table('ai_usage', function (Blueprint $t) {
            $t->dropIndex(['created_at', 'status']);
            $t->dropIndex(['provider']);
            $t->dropColumn(['provider', 'operation', 'error_category']);
        });
    }
};
