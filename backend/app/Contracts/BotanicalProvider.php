<?php

namespace App\Contracts;

use App\Models\Conversation;

interface BotanicalProvider
{
    public function respond(Conversation $conversation): array;
}
