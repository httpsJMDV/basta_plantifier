<?php

namespace App\Services;

use App\Models\Conversation;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationHistory
{
    public const DEFAULTS = ['q' => '', 'status' => 'active', 'activity' => 'all', 'group' => 'date', 'sort' => 'activity', 'timezone' => 'UTC'];

    public function filters(Request $request): array
    {
        $values = $request->validate([
            'q' => 'sometimes|nullable|string|max:200',
            'status' => ['sometimes', Rule::in(['active', 'archived', 'all'])],
            'activity' => ['sometimes', Rule::in(['all', 'today', '7', '30'])],
            'group' => ['sometimes', Rule::in(['date', 'none'])],
            'sort' => ['sometimes', Rule::in(['activity', 'created', 'title'])],
            'timezone' => ['sometimes', Rule::in(DateTimeZone::listIdentifiers())],
        ]);
        $filters = array_merge(self::DEFAULTS, $request->session()->get('history_filters', []), $values);
        $filters['q'] = trim($filters['q'] ?? '');
        $request->session()->put('history_filters', $filters);

        return $filters;
    }

    public function query(int $owner, array $filters): Builder
    {
        $query = Conversation::query()->where('user_id', $owner);
        if ($filters['status'] === 'active') {
            $query->whereNull('archived_at');
        } elseif ($filters['status'] === 'archived') {
            $query->whereNotNull('archived_at');
        }
        if ($filters['q'] !== '') {
            $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%']);
        }
        if ($filters['activity'] !== 'all') {
            $days = $filters['activity'] === 'today' ? 1 : (int) $filters['activity'];
            $start = CarbonImmutable::now($filters['timezone'])->startOfDay()->subDays($days - 1)->utc();
            $end = CarbonImmutable::now($filters['timezone'])->addDay()->startOfDay()->utc();
            $query->where('updated_at', '>=', $start)->where('updated_at', '<', $end);
        }
        $column = ['activity' => 'updated_at', 'created' => 'created_at', 'title' => 'title'][$filters['sort']];

        return $query->orderBy($column, $column === 'title' ? 'asc' : 'desc')->orderByDesc('id');
    }
}
