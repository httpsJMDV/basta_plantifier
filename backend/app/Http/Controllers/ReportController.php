<?php

namespace App\Http\Controllers;

use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function store(Request $r, SocialGraph $graph)
    {
        $data = $r->validate(['subject_type' => 'required|in:post,comment,story,pin,user,message', 'subject_id' => 'required|integer', 'reason' => 'required|string|min:10|max:3000']);
        $table = ['post' => 'posts', 'comment' => 'comments', 'story' => 'stories', 'pin' => 'map_pins', 'user' => 'users', 'message' => 'direct_messages'][$data['subject_type']];
        $subject = DB::table($table)->find($data['subject_id']);
        abort_unless($subject, 404);
        $uid = $r->user()->id;
        $author = $data['subject_type'] === 'user' ? $subject->id : $subject->user_id;
        abort_if($graph->blocked($uid, $author) || DB::table('users')->where('id', $author)->whereNotNull('suspended_at')->exists(), 404);
        if ($data['subject_type'] === 'message') {
            $thread = DB::table('threads')->find($subject->thread_id);
            abort_unless($thread && $graph->canReadThread($uid, $thread), 404);
        } elseif ($data['subject_type'] === 'pin') {
            abort_unless($subject->user_id === $uid || in_array($subject->visibility, ['public_approximate', 'public_exact']), 404);
        } elseif ($data['subject_type'] === 'story') {
            abort_unless($subject->expires_at > now()->toDateTimeString() && ($subject->audience === 'public' || $subject->user_id === $uid || in_array($subject->user_id, $graph->friendIds($uid), true)), 404);
        } elseif ($data['subject_type'] === 'comment') {
            abort_unless(DB::table('posts')->where('id', $subject->post_id)->where('status', 'published')->exists(), 404);
        } elseif ($data['subject_type'] === 'post') {
            abort_unless($subject->status === 'published', 404);
        }
        abort_if(DB::table('reports')->where('user_id', $uid)->where('subject_type', $data['subject_type'])->where('subject_id', $data['subject_id'])->where('status', 'pending')->exists(), 409, 'Your report for this item is already awaiting review.');
        DB::table('reports')->insert(['user_id' => $uid, ...$data, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Report submitted. Only the reported item is available to the moderation workflow.']);
    }
}
