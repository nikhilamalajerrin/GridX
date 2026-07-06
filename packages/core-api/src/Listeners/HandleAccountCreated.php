<?php

namespace GridX\Listeners;

use GridX\Events\AccountCreated;
use GridX\Models\VerificationCode;

class HandleAccountCreated
{
    /**
     * Handle the event.
     *
     * @param object $event
     *
     * @return void
     */
    public function handle(AccountCreated $event)
    {
        // Send user a verification email
        $user = $event->user;

        if ($user && $user->isNotAdmin()) {
            // Create and send verification code
            try {
                VerificationCode::generateEmailVerificationFor($user);
            } catch (\Throwable $e) {
                // If phone number is supplied send via SMS
                if ($user->phone) {
                    try {
                        VerificationCode::generateSmsVerificationFor($user);
                    } catch (\Throwable $e) {
                        // silence
                    }
                }
            }
        }
    }
}
