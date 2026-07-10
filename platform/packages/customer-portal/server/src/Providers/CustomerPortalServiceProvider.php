<?php

namespace GridX\CustomerPortal\Providers;

use GridX\FleetOps\Models\Issue;
use GridX\FleetOps\Providers\FleetOpsServiceProvider;
use GridX\Ledger\Providers\LedgerServiceProvider;
use GridX\Models\Comment;
use GridX\Providers\CoreServiceProvider;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('Customer Portal cannot be loaded without `gridx/core-api` installed!');
}

if (!class_exists(FleetOpsServiceProvider::class)) {
    throw new \Exception('Customer Portal cannot be loaded without `gridx/fleetops-api` installed!');
}

if (!class_exists(LedgerServiceProvider::class)) {
    throw new \Exception('Customer Portal cannot be loaded without `gridx/ledger-api` installed!');
}

/**
 * Starter extension service provider.
 */
class CustomerPortalServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [
        Comment::class => \GridX\CustomerPortal\Observers\CommentObserver::class,
        Issue::class   => \GridX\CustomerPortal\Observers\IssueObserver::class,
    ];

    /**
     * Register any application services.
     *
     * Within the register method, you should only bind things into the
     * service container. You should never attempt to register any event
     * listeners, routes, or any other piece of functionality within the
     * register method.
     *
     * More information on this can be found in the Laravel documentation:
     * https://laravel.com/docs/8.x/providers
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception if the `gridx/core-api` package is not installed
     */
    public function boot()
    {
        $this->registerObservers();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
    }
}
