<?php

namespace GridX\Observers;

use GridX\Events\ChatParticipantAdded;
use GridX\Events\ChatParticipantRemoved;
use GridX\Models\ChatLog;
use GridX\Models\ChatParticipant;

class ChatParticipantObserver
{
    /**
     * Handle the ChatParticipant "created" event.
     *
     * @return void
     */
    public function created(ChatParticipant $chatParticipant)
    {
        event(new ChatParticipantAdded($chatParticipant));
        ChatLog::participantAdded(ChatParticipant::current($chatParticipant->chat_channel_uuid), $chatParticipant);
    }

    /**
     * Handle the ChatParticipant "deleted" event.
     *
     * @return void
     */
    public function deleted(ChatParticipant $chatParticipant)
    {
        event(new ChatParticipantRemoved($chatParticipant));
        $currentParticipant = ChatParticipant::current($chatParticipant->chat_channel_uuid, true);
        // hotfix for leaving chat
        if (session('user') === $chatParticipant->user_uuid) {
            $currentParticipant = $chatParticipant;
        }
        ChatLog::participantRemoved($currentParticipant, $chatParticipant);
    }
}
