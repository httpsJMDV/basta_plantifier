<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminQaSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.name') !== 'PlantifierQA' || config('database.default') !== 'sqlite' || ! str_ends_with((string) config('database.connections.sqlite.database'), 'amaru-qa.sqlite')) {
            throw new \RuntimeException('Admin fixtures are restricted to the dedicated QA SQLite database.');
        }
        $this->call(AmaruQaSeeder::class);
        $existing = DB::table('super_admin')->first();
        if ($existing && User::findOrFail($existing->user_id)->email !== 'qa-admin@example.test') {
            throw new \RuntimeException('The QA admin slot belongs to a different account.');
        }
        $admin = User::firstOrCreate(['email' => 'qa-admin@example.test'], ['name' => 'QA Administrator', 'password' => Hash::make('Botanical12345')]);
        $admin->forceFill(['suspended_at' => null, 'onboarded_at' => now(), 'email_verified_at' => now(), 'local_password_set_at' => now(), 'totp_secret' => 'JBSWY3DPEHPK3PXP', 'last_totp_counter' => null, 'recovery_codes' => [Hash::make('qa-admin-recovery-only')]])->save();
        if (! $existing) {
            DB::table('super_admin')->insert(['id' => 1, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $sender = User::where('email', 'qa-sender@example.test')->firstOrFail();
        $receiver = User::where('email', 'qa-receiver@example.test')->firstOrFail();
        foreach ([$sender, $receiver] as $user) {
            $user->forceFill(['suspended_at' => null])->save();
        }
        $plant = $sender->plants()->firstOrCreate(['common_name' => 'QA reviewed fern'], ['relationship' => 'growing']);
        $postId = DB::table('posts')->where('body', 'QA admin review discovery')->value('id');
        $postData = ['user_id' => $sender->id, 'body' => 'QA admin review discovery', 'kind' => 'post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()];
        if ($postId) {
            DB::table('posts')->where('id', $postId)->update($postData);
        } else {
            $postId = DB::table('posts')->insertGetId($postData);
        }
        foreach (['exact' => 'public_exact', 'approximate' => 'public_approximate', 'sensitive' => 'public_exact', 'private' => 'private'] as $label => $visibility) {
            $title = 'QA admin '.$label.' location';
            $values = ['user_id' => $sender->id, 'plant_record_id' => $plant->id, 'title' => $title, 'area' => 'Laguna', 'visibility' => $visibility, 'sensitive' => $label === 'sensitive', 'latitude' => 14.284321, 'longitude' => 121.425678, 'observed_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()];
            DB::table('map_pins')->updateOrInsert(['title' => $title, 'user_id' => $sender->id], $values);
        }
        $pin = DB::table('map_pins')->where('title', 'QA admin exact location')->value('id');
        $comment = DB::table('comments')->where('body', 'QA reported comment')->value('id');
        if (! $comment) {
            $comment = DB::table('comments')->insertGetId(['post_id' => $postId, 'user_id' => $sender->id, 'body' => 'QA reported comment', 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('comments')->where('id', $comment)->update(['hidden_at' => null]);
        }
        $media = Media::firstOrCreate(['user_id' => $sender->id, 'path' => 'qa/admin/plant.jpg'], ['mime' => 'image/jpeg', 'width' => 100, 'height' => 100]);
        Storage::disk('local')->put('qa/admin/plant.jpg', file_get_contents(base_path('../frontend/tests/browser/fixture-plant.jpg')));
        $story = DB::table('stories')->where('caption', 'QA reported story')->value('id');
        $storyData = ['user_id' => $sender->id, 'media_id' => $media->id, 'caption' => 'QA reported story', 'audience' => 'public', 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()];
        if ($story) {
            DB::table('stories')->where('id', $story)->update($storyData);
        } else {
            $story = DB::table('stories')->insertGetId($storyData);
        }
        $thread = DB::table('threads')->where('first_user_id', $sender->id)->where('second_user_id', $receiver->id)->first();
        $threadId = $thread?->id ?? DB::table('threads')->insertGetId(['first_user_id' => $sender->id, 'second_user_id' => $receiver->id, 'created_at' => now(), 'updated_at' => now()]);
        $message = DB::table('direct_messages')->where('body', 'QA reported message only')->value('id');
        if (! $message) {
            $message = DB::table('direct_messages')->insertGetId(['thread_id' => $threadId, 'user_id' => $sender->id, 'body' => 'QA reported message only', 'media_id' => $media->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['post' => $postId, 'comment' => $comment, 'pin' => $pin, 'story' => $story, 'user' => $sender->id, 'message' => $message] as $type => $target) {
            $record = DB::table('reports')->where('subject_type', $type)->where('subject_id', $target)->where('reason', 'QA admin '.$type.' review')->first();
            $values = ['user_id' => $receiver->id, 'subject_type' => $type, 'subject_id' => $target, 'reason' => 'QA admin '.$type.' review', 'status' => 'pending', 'resolution' => null, 'created_at' => now(), 'updated_at' => now()];
            if ($record) {
                DB::table('report_decisions')->where('report_id', $record->id)->delete();
                DB::table('reports')->where('id', $record->id)->update($values);
            } else {
                DB::table('reports')->insert($values);
            }
        }
        $conversation = $sender->conversations()->firstOrCreate(['title' => 'QA admin correction context']);
        $original = ['source' => 'Pl@ntNet', 'scientific_name' => 'Jasminum sambac', 'score' => 0.6, 'status' => 'ambiguous', 'common_name' => 'Arabian jasmine', 'family' => 'Oleaceae', 'traits' => 'General botanical traits', 'habitat' => 'General habitat', 'care' => 'Conditional care guidance', 'safety' => 'Do not consume based on identification.', 'uncertainty' => 'This is a tentative identification.', 'alternatives' => []];
        $originalMessage = $conversation->messages()->firstOrCreate(['body' => 'QA original identification unchanged'], ['role' => 'assistant', 'identification' => $original]);
        $originalMessage->forceFill(['identification' => $original])->save();
        DB::table('corrections')->updateOrInsert(['chat_message_id' => $originalMessage->id], ['user_id' => $sender->id, 'original' => json_encode($original), 'suggested_name' => 'QA proposed species', 'reason' => 'Compare the leaf and flower details.', 'status' => 'pending', 'review_notes' => null, 'reviewed_at' => null, 'reviewed_by' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('system_settings')->updateOrInsert(['key' => 'ai_paused'], ['value' => '0', 'created_at' => now(), 'updated_at' => now()]);
    }
}
