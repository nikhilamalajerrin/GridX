<?php

namespace GridX\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        /*
         * GridX Events
         */
        \GridX\Events\ResourceLifecycleEvent::class => [\GridX\Listeners\SendResourceLifecycleWebhook::class],
        \GridX\Events\AccountCreated::class         => [\GridX\Listeners\HandleAccountCreated::class],

        /*
         * Framework Events
         */
        \Illuminate\Notifications\Events\BroadcastNotificationCreated::class => [\GridX\Listeners\TriggerPublicNotificationBroadcast::class],

        /*
         * Webhook Events
         */
        \GridX\Webhook\Events\WebhookCallSucceededEvent::class   => [\GridX\Listeners\LogSuccessfulWebhook::class],
        \GridX\Webhook\Events\WebhookCallFailedEvent::class      => [\GridX\Listeners\LogFailedWebhook::class],
        \GridX\Webhook\Events\FinalWebhookCallFailedEvent::class => [\GridX\Listeners\LogFinalWebhookAttempt::class],
    ];
}
