<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username')->nullable()->unique();
            $t->string('google_id')->nullable()->unique();
            $t->text('bio')->nullable();
            $t->string('municipality')->nullable();
            $t->json('interests')->nullable();
            $t->string('purpose')->nullable();
            $t->string('experience')->nullable();
            $t->timestamp('onboarded_at')->nullable();
            $t->boolean('discovery_enabled')->default(false);
            $t->string('friend_request_privacy')->default('everyone');
            $t->timestamp('suspended_at')->nullable();
            $t->text('totp_secret')->nullable();
            $t->text('recovery_codes')->nullable();
        });
        // The only permitted primary key is 1. A second admin row cannot exist.
        Schema::create('super_admin', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->timestamps();
        });
        DB::statement('CREATE TRIGGER super_admin_singleton BEFORE INSERT ON super_admin '.(DB::getDriverName() === 'sqlite'
            ? "WHEN NEW.id <> 1 BEGIN SELECT RAISE(ABORT, 'Only one Super Admin is permitted'); END"
            : "FOR EACH ROW BEGIN IF NEW.id <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one Super Admin is permitted'; END IF; END"));
        DB::statement('CREATE TRIGGER super_admin_immutable BEFORE UPDATE ON super_admin '.(DB::getDriverName() === 'sqlite'
            ? "BEGIN SELECT RAISE(ABORT, 'Use protected admin recovery'); END"
            : "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Use protected admin recovery'"));
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title')->default('New discovery');
            $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('mime');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->string('role');
            $t->text('body');
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->json('identification')->nullable();
            $t->string('status')->default('complete');
            $t->timestamps();
        });
        Schema::create('plant_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chat_message_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->string('common_name');
            $t->string('scientific_name')->nullable();
            $t->string('family')->nullable();
            $t->text('description')->nullable();
            $t->text('care')->nullable();
            $t->text('safety')->nullable();
            $t->json('provenance')->nullable();
            $t->string('relationship')->default('discovered');
            $t->boolean('favorite')->default(false);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plant_record_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plant_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->date('observed_on');
            $t->text('body');
            $t->decimal('height_cm', 10, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('map_pins', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plant_record_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('area')->nullable();
            $t->text('description')->nullable();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->string('visibility')->default('private');
            $t->boolean('sensitive')->default(false);
            $t->date('observed_on');
            $t->timestamps();
            $t->index(['visibility', 'observed_on']);
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->string('kind')->default('post');
            $t->string('status')->default('published');
            $t->string('area')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->string('species')->nullable();
            $t->boolean('accepted')->default(false);
            $t->timestamps();
        });
        Schema::create('suggestion_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unique(['comment_id', 'user_id']);
        });
        Schema::create('stories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $t->string('caption')->nullable();
            $t->string('audience')->default('friends');
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('relationships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $t->string('kind');
            $t->string('status')->default('pending');
            $t->timestamps();
            $t->unique(['user_id', 'target_id', 'kind']);
        });
        Schema::create('threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('first_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('second_user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['first_user_id', 'second_user_id']);
        });
        Schema::create('direct_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body')->nullable();
            $t->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plant_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('direct_message_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $t->json('original');
            $t->string('suggested_name');
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->text('resolution')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_usage', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('model');
            $t->string('status');
            $t->unsignedInteger('tokens')->default(0);
            $t->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['system_settings', 'ai_usage', 'audit_events', 'reports', 'corrections', 'shares', 'direct_messages', 'threads', 'relationships', 'stories', 'suggestion_votes', 'comments', 'posts', 'map_pins', 'observations', 'notes', 'plant_records', 'chat_messages', 'media', 'conversations', 'super_admin'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['username', 'google_id', 'bio', 'municipality', 'interests', 'purpose', 'experience', 'onboarded_at', 'discovery_enabled', 'friend_request_privacy', 'suspended_at', 'totp_secret', 'recovery_codes']));
    }
};
