<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $u, Conversation $c): bool
    {
        return $u->id === $c->user_id;
    }

    public function update(User $u, Conversation $c): bool
    {
        return $this->view($u, $c);
    }

    public function delete(User $u, Conversation $c): bool
    {
        return $this->view($u, $c);
    }
}
