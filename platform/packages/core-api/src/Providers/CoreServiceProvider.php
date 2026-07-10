<?php

namespace GridX\Providers;

use GridX\Models\Setting;
use GridX\Support\EnvironmentMapper;
use GridX\Support\NotificationRegistry;
use GridX\Support\Reporting\ReportSchemaRegistry;
use GridX\Support\Telemetry;
use GridX\Support\Utils;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

/**
 * CoreServiceProvider.
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [
        \GridX\Models\Company::class                 => \GridX\Observers\CompanyObserver::class,
        \GridX\Models\User::class                    => \GridX\Observers\UserObserver::class,
        \GridX\Models\ApiCredential::class           => \GridX\Observers\ApiCredentialObserver::class,
        \GridX\Models\Notification::class            => \GridX\Observers\NotificationObserver::class,
        \GridX\Models\ChatParticipant::class         => \GridX\Observers\ChatParticipantObserver::class,
        \Spatie\Activitylog\Models\Activity::class       => \GridX\Observers\ActivityObserver::class,
    ];

    /**
     * The global middlewares to be registered with the service provider.
     *
     * @var array
     */
    public $globalMiddlewares = [
        \GridX\Http\Middleware\RequestTimer::class,
        \GridX\Http\Middleware\ResetJsonResourceWrap::class,
        \GridX\Http\Middleware\MergeConfigFromSettings::class,
        \GridX\Http\Middleware\EnsureGridXConfigured::class,
        \GridX\Http\Middleware\AttachCacheHeaders::class,
    ];

    /**
     * The middleware groups registered with the service provider.
     *
     * @var array
     */
    public $middleware = [
        'gridx.protected' => [
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            'auth:sanctum',
            \GridX\Http\Middleware\SetupGridXSession::class,
            \GridX\Http\Middleware\AuthorizationGuard::class,
            \GridX\Http\Middleware\TrackPresence::class,
            \GridX\Http\Middleware\ValidateETag::class,
        ],
        'gridx.api' => [
            \GridX\Http\Middleware\ThrottleRequests::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \GridX\Http\Middleware\AuthenticateOnceWithBasicAuth::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \GridX\Http\Middleware\LogApiRequests::class,
        ],
    ];

    /**
     * The console commands registered with the service provider.
     *
     * @var array
     */
    public $commands = [
        \GridX\Console\Commands\Recovery::class,
        \GridX\Console\Commands\QueueStatusCommand::class,
        \GridX\Console\Commands\AssignAdminRoles::class,
        \GridX\Console\Commands\ForceResetDatabase::class,
        \GridX\Console\Commands\CreateDatabase::class,
        \GridX\Console\Commands\SeedDatabase::class,
        \GridX\Console\Commands\MigrateSandbox::class,
        \GridX\Console\Commands\InitializeSandboxKeyColumn::class,
        \GridX\Console\Commands\SyncSandbox::class,
        \GridX\Console\Commands\CreatePermissions::class,
        \GridX\Console\Commands\NotifyInstalled::class,
        \GridX\Console\Commands\FixUserCompanies::class,
        \GridX\Console\Commands\PurgeApiLogs::class,
        \GridX\Console\Commands\PurgeWebhookLogs::class,
        \GridX\Console\Commands\PurgeActivityLogs::class,
        \GridX\Console\Commands\PurgeScheduledTaskLogs::class,
        \GridX\Console\Commands\PurgeOrphanedModelRecords::class,
        \GridX\Console\Commands\BackupDatabase\MysqlS3Backup::class,
        \GridX\Console\Commands\TelemetryPing::class,
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
        $this->mergeConfigFrom(__DIR__ . '/../../config/database.connections.php', 'database.connections');
        $this->mergeConfigFrom(__DIR__ . '/../../config/database.redis.php', 'database.redis');
        $this->mergeConfigFrom(__DIR__ . '/../../config/broadcasting.connections.php', 'broadcasting.connections');
        $this->mergeConfigFrom(__DIR__ . '/../../config/gridx.php', 'gridx');
        $this->mergeConfigFrom(__DIR__ . '/../../config/api.php', 'api');
        $this->mergeConfigFrom(__DIR__ . '/../../config/auth.php', 'auth');
        $this->mergeConfigFrom(__DIR__ . '/../../config/sanctum.php', 'sanctum');
        $this->mergeConfigFrom(__DIR__ . '/../../config/twilio.php', 'twilio');
        $this->mergeConfigFrom(__DIR__ . '/../../config/twilio-notification-channel.php', 'twilio-notification-channel');
        $this->mergeConfigFrom(__DIR__ . '/../../config/firebase.php', 'firebase');
        $this->mergeConfigFrom(__DIR__ . '/../../config/webhook-server.php', 'webhook-server');
        $this->mergeConfigFrom(__DIR__ . '/../../config/permission.php', 'permission');
        $this->mergeConfigFrom(__DIR__ . '/../../config/activitylog.php', 'activitylog');
        $this->mergeConfigFrom(__DIR__ . '/../../config/schedule-monitor.php', 'schedule-monitor');
        $this->mergeConfigFrom(__DIR__ . '/../../config/excel.php', 'excel');
        $this->mergeConfigFrom(__DIR__ . '/../../config/sentry.php', 'sentry');
        $this->mergeConfigFrom(__DIR__ . '/../../config/laravel-mysql-s3-backup.php', 'laravel-mysql-s3-backup');
        $this->mergeConfigFrom(__DIR__ . '/../../config/responsecache.php', 'responsecache');
        $this->mergeConfigFrom(__DIR__ . '/../../config/image.php', 'image');
        $this->mergeConfigFrom(__DIR__ . '/../../config/sms.php', 'sms');

        // setup report schema registry
        $this->app->singleton(ReportSchemaRegistry::class, function () {
            return new ReportSchemaRegistry();
        });

        // register file resolver service
        $this->app->singleton(\GridX\Services\FileResolverService::class, function ($app) {
            return new \GridX\Services\FileResolverService();
        });

        // register template render service
        $this->app->singleton(\GridX\Services\TemplateRenderService::class, function ($app) {
            return new \GridX\Services\TemplateRenderService();
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->__hotfixCommonmarkDeprecation();
        $this->registerCommands();
        $this->scheduleCommands(function ($schedule) {
            $schedule->command('cache:prune-stale-tags')->hourly();
            $schedule->command('model:prune', ['--model' => MonitoredScheduledTaskLogItem::class])->twiceDaily(1, 13);
            $schedule->command('purge:api-logs --force --no-interaction --days 2')->twiceDaily(1, 13);
            $schedule->command('purge:webhook-logs --force --no-interaction --days 2')->twiceDaily(1, 13);
            $schedule->command('purge:activity-logs --force --no-interaction --days 2')->twiceDaily(1, 13);
            $schedule->command('purge:scheduled-task-logs --force --no-interaction --days 1')->twiceDaily(1, 13);
            $schedule->command('telemetry:ping')->daily();
            $schedule->job(new \GridX\Jobs\MaterializeSchedulesJob())->dailyAt('01:00')->name('materialize-schedules')->withoutOverlapping();
            // Keep sandbox users/companies in sync with production so that
            // test-mode API key creation (and other sandbox operations) never
            // fail due to missing foreign-key referenced rows. Hourly cadence
            // is a good balance: frequent enough that new users/companies are
            // available in sandbox within an hour of being created in
            // production, but infrequent enough to avoid unnecessary DB load.
            $schedule->command('sandbox:sync')->hourly()->name('sandbox-sync')->withoutOverlapping();
        });
        $this->registerObservers();
        $this->registerExpansionsFrom();
        $this->registerMiddleware();
        $this->registerNotifications();
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->loadViewsFrom(__DIR__ . '/../../views', 'gridx');
        $this->registerCustomBladeComponents();
        $this->mergeConfigFromSettings();
        $this->pingTelemetry();
    }

    /**
     * Registers GridX provided blade components.
     *
     * @return void
     */
    public function registerCustomBladeComponents()
    {
        Blade::component('gridx::layout.mail', 'mail-layout');
    }

    /**
     * Merge configuration values from application settings.
     *
     * This function iterates through a predefined list of settings keys,
     * retrieves their values from the system settings, and updates the
     * Laravel configuration values accordingly. For some settings, it
     * also updates corresponding environment variables.
     *
     * The settings keys and the corresponding config keys are defined
     * in the $settings array. The $putsenv array defines the settings
     * keys that also need to update environment variables and maps each
     * settings key to the environment variables that need to be updated.
     *
     * @return void
     */
    public function mergeConfigFromSettings()
    {
        EnvironmentMapper::mergeConfigFromSettingsOptimized();
    }

    /**
     * Registers all class extension macros from the specified path and namespace.
     *
     * @param string|null $from      The path to load the macros from. If null, the default path is used.
     * @param string|null $namespace The namespace to load the macros from. If null, the default namespaces are used.
     */
    public function registerExpansionsFrom($from = null, $namespace = null): void
    {
        if (is_array($from)) {
            foreach ($from as $frm) {
                $this->registerExpansionsFrom($frm);
            }

            return;
        }

        try {
            $macros = new \DirectoryIterator($from ?? __DIR__ . '/../Expansions');
        } catch (\UnexpectedValueException $e) {
            // no expansions
            return;
        }

        $packageNamespace = $this->findPackageNamespace($from);

        foreach ($macros as $macro) {
            if (!$macro->isFile()) {
                continue;
            }

            $className = $macro->getBasename('.php');

            if ($namespace === null) {
                // resolve namespace
                $namespaces = ['GridX\\Expansions\\', 'GridX\\Macros\\', 'GridX\\Mixins\\'];

                if ($packageNamespace) {
                    $namespaces[] = $packageNamespace . '\\Expansions\\';
                    $namespaces[] = $packageNamespace . '\\Macros\\';
                    $namespaces[] = $packageNamespace . '\\Mixins\\';
                }

                $namespace = Arr::first(
                    $namespaces,
                    function ($ns) use ($className) {
                        return Utils::classExists($ns . $className);
                    }
                );

                if (!$namespace) {
                    continue;
                }
            }

            $class  = $namespace . $className;
            $target = $class::target();

            if (!Utils::classExists($target)) {
                continue;
            }

            try {
                $target::expand(new $class());
            } catch (\Throwable $e) {
                try {
                    $target::mixin(new $class());
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private function registerNotifications()
    {
        NotificationRegistry::register([
            \GridX\Notifications\UserCreated::class,
            \GridX\Notifications\UserAcceptedCompanyInvite::class,
        ]);
    }

    /**
     * Register the middleware groups defined by the service provider.
     */
    public function registerMiddleware(): void
    {
        // Global middlewares
        if ($this->globalMiddlewares) {
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            foreach ($this->globalMiddlewares as $middleware) {
                $kernel->pushMiddleware($middleware);
            }
        }

        foreach ($this->middleware as $group => $middlewares) {
            foreach ($middlewares as $middleware) {
                $this->app->router->pushMiddlewareToGroup($group, $middleware);
            }
        }
    }

    /**
     * Register the model observers defined by the service provider.
     */
    public function registerObservers(): void
    {
        foreach ($this->observers as $model => $observer) {
            if (Utils::classExists($model)) {
                $model::observe($observer);
            }
        }
    }

    /**
     * Load configuration files from the specified directory.
     *
     * @param string $path
     *
     * @return void
     */
    protected function loadConfigFromDirectory($path)
    {
        $files = glob($path . '/*.php');

        foreach ($files as $file) {
            $this->mergeConfigFrom(
                $file,
                pathinfo($file, PATHINFO_FILENAME)
            );
        }
    }

    /**
     * Register the console commands defined by the service provider.
     */
    public function registerCommands(): void
    {
        $this->commands($this->commands ?? []);
    }

    /**
     * Schedule commands within the service provider.
     *
     * This method allows child service providers to easily schedule their commands
     * by providing a callback that receives the Laravel scheduler instance.
     *
     * @param callable|null $callback A callback function that receives the Laravel scheduler instance.
     *                                The callback is used to define the scheduling of commands.
     *                                If no callback is provided, no scheduling will occur.
     *
     * @example
     * $this->scheduleCommands(function ($schedule) {
     *     $schedule->command('your-package:command')->daily();
     * });
     */
    public function scheduleCommands(?callable $callback = null): void
    {
        // Skip everything in CI / PHPUnit / pest
        if (app()->environment(['testing', 'ci']) || env('CI') || Setting::doesntHaveConnection()) {
            return;
        }

        $this->app->booted(function () use ($callback) {
            $schedule = $this->app->make(Schedule::class);

            if (is_callable($callback)) {
                $callback($schedule);
            }
        });
    }

    /**
     * Find the package namespace for a given path.
     *
     * @param string|null $path The path to search for the package namespace. If null, no namespace is returned.
     *
     * @return string|null the package namespace, or null if the path is not valid
     */
    private function findPackageNamespace($path = null): ?string
    {
        return Utils::findPackageNamespace($path);
    }

    /**
     * Apply a hotfix for a deprecation issue in the league/commonmark package.
     *
     * The league/commonmark package triggers deprecation notices using the `trigger_deprecation` function,
     * which interferes with the normal application flow. This hotfix introduces a custom implementation
     * of `trigger_deprecation` that specifically skips triggering deprecations for the league/commonmark package.
     * This allows the application to continue running without being affected by the league/commonmark deprecations.
     */
    private function __hotfixCommonmarkDeprecation(): void
    {
        if (!function_exists('trigger_deprecation')) {
            /**
             * Custom implementation of trigger_deprecation.
             *
             * @param string $package The name of the Composer package
             * @param string $version The version of the package
             * @param string $message The message of the deprecation
             * @param mixed  ...$args Values to insert in the message using printf() formatting
             */
            function trigger_deprecation(string $package, string $version, string $message, mixed ...$args): void
            {
                // Check if the package is "league/commonmark" and skip triggering the deprecation
                if ($package === 'league/commonmark') {
                    return;
                }

                // Otherwise, trigger the deprecation as usual
                @trigger_error(($package || $version ? "Since $package $version: " : '') . ($args ? vsprintf($message, $args) : $message), \E_USER_DEPRECATED);
            }
        }
    }

    /**
     * Sends telemetry ping.
     *
     * @return void
     */
    public function pingTelemetry()
    {
        if (app()->runningInConsole() || env('CI') || Setting::doesntHaveConnection()) {
            return;
        }

        return Telemetry::ping();
    }
}
