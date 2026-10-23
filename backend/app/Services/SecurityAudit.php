<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SecurityAudit
{
    public function write(?int $actor, string $action, ?string $subject = null, array $metadata = []): void
    {
        $safe = array_intersect_key($metadata, array_flip(['reason', 'action', 'status', 'from_status', 'subject_type', 'subject_id', 'category', 'response_code', 'operation', 'kind', 'changed_keys']));
        foreach ($safe as $key => $value) {
            if ($key === 'changed_keys') {
                $safe[$key] = array_values(array_intersect(is_array($value) ? $value : [], ['announcement', 'ai_paused']));

                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                unset($safe[$key]);

                continue;
            }
            if (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, 3000);
            }
        }
        DB::table('audit_events')->insert(['user_id' => $actor, 'action' => $action, 'subject' => $subject, 'metadata' => $safe ? json_encode($safe, JSON_THROW_ON_ERROR) : null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
