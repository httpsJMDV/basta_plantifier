<?php

namespace App\Services;

use App\Contracts\BotanicalProvider;
use App\Models\Conversation;

class HybridBotanicalProvider implements BotanicalProvider
{
    public function __construct(private PlantNetIdentifier $identifier, private GroqProvider $groq) {}

    public function respond(Conversation $conversation): array
    {
        $this->groq->ensureAvailable();
        $latestUser = $conversation->messages()->where('role', 'user')->reorder()->latest('id')->with('media')->first();
        $newPhoto = $latestUser?->media !== null;
        $imageMessage = $newPhoto ? $latestUser : $conversation->messages()->where('role', 'user')->whereNotNull('media_id')->reorder()->latest('id')->first();
        if ($newPhoto && $imageMessage->plantnet_result === null) {
            $photos = $imageMessage->attachments;
            $result = $this->identifier->identify($photos->isEmpty() ? [$imageMessage->media] : $photos->all());
            $imageMessage->forceFill(['plantnet_result' => $result])->save();
        }

        return $this->groq->respond($conversation, $imageMessage?->plantnet_result, $newPhoto);
    }
}
