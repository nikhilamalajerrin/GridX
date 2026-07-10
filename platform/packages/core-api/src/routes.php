<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

if (env('APP_DEBUG') === true) {
    Route::get('test', 'GridX\Http\Controllers\Controller@test');
}

Route::prefix(config('gridx.api.routing.prefix', '/'))->namespace('GridX\Http\Controllers')->group(
    function ($router) {
        $router->get('/', 'Controller@hello');

        /*
        |--------------------------------------------------------------------------
        | Public/Consumable Routes
        |--------------------------------------------------------------------------
        |
        | Routes for users and public applications to consume.
        */
        $router->prefix('v1')
            ->namespace('Api\v1')
            ->middleware(['gridx.api'])
            ->group(function ($router) {
                $router->group(
                    ['prefix' => 'organizations'],
                    function ($router) {
                        $router->get('current', 'OrganizationController@getCurrent');
                    }
                );
                $router->group(
                    ['prefix' => 'files'],
                    function ($router) {
                        $router->post('/', 'FileController@create');
                        $router->post('base64', 'FileController@createFromBase64');
                        $router->put('{id}', 'FileController@update');
                        $router->get('{id}/download', 'FileController@download');
                        $router->get('/', 'FileController@query');
                        $router->get('{id}', 'FileController@find');
                        $router->delete('{id}', 'FileController@delete');
                    }
                );
                $router->group(
                    ['prefix' => 'chat-channels'],
                    function ($router) {
                        $router->get('available-participants', 'ChatChannelController@getAvailablePartificants');
                        $router->post('{id}/send-message', 'ChatChannelController@sendMessage');
                        $router->delete('delete-message/{chatMessageId}', 'ChatChannelController@deleteMessage');
                        $router->post('read-message/{chatMessageId}', 'ChatChannelController@createReadReceipt');
                        $router->post('/', 'ChatChannelController@create');
                        $router->put('{id}', 'ChatChannelController@update');
                        $router->get('/', 'ChatChannelController@query');
                        $router->get('{id}', 'ChatChannelController@find');
                        $router->delete('{id}', 'ChatChannelController@delete');
                        $router->post('{id}/add-participant', 'ChatChannelController@addParticipant');
                        $router->delete('remove-participant/{participantId}', 'ChatChannelController@removeParticipant');
                    }
                );
                $router->group(
                    ['prefix' => 'comments'],
                    function ($router) {
                        $router->post('/', 'CommentController@create');
                        $router->put('{id}', 'CommentController@update');
                        $router->get('/', 'CommentController@query');
                        $router->get('{id}', 'CommentController@find');
                        $router->delete('{id}', 'CommentController@delete');
                    }
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Internal Routes
        |--------------------------------------------------------------------------
        |
        | Primary internal routes for console.
        */
        $router->prefix(config('gridx.api.routing.internal_prefix', 'int'))->namespace('Internal')->group(
            function ($router) {
                $router->prefix('v1')->namespace('v1')->group(
                    function ($router) {
                        $router->gridxAuthRoutes();
                        $router->group(
                            ['prefix' => 'onboard', 'middleware' => [GridX\Http\Middleware\ThrottleRequests::class]],
                            function ($router) {
                                $router->get('should-onboard', 'OnboardController@shouldOnboard');
                                $router->post('create-account', 'OnboardController@createAccount');
                                $router->post('verify-email', 'OnboardController@verifyEmail');
                                $router->post('send-verification-sms', 'OnboardController@sendVerificationSms');
                                $router->post('send-verification-email', 'OnboardController@sendVerificationEmail');
                            }
                        );
                        $router->group(
                            ['prefix' => 'lookup', 'middleware' => [GridX\Http\Middleware\ThrottleRequests::class]],
                            function ($router) {
                                $router->get('timezones', 'LookupController@timezones');
                                $router->get('whois', 'LookupController@whois');
                                $router->get('currencies', 'LookupController@currencies');
                                $router->get('countries', 'LookupController@countries');
                                $router->get('country/{code}', 'LookupController@country');
                                $router->get('gridx-blog', 'LookupController@gridxBlog');
                                $router->get('font-awesome-icons', 'LookupController@fontAwesomeIcons');
                            }
                        );
                        $router->group(
                            ['prefix' => 'users', 'middleware' => [GridX\Http\Middleware\ThrottleRequests::class]],
                            function ($router) {
                                $router->post('accept-company-invite', 'UserController@acceptCompanyInvite');
                            }
                        );
                        $router->group(
                            ['prefix' => 'companies', 'middleware' => [GridX\Http\Middleware\ThrottleRequests::class]],
                            function ($router) {
                                $router->get('find/{id}', 'CompanyController@findCompany');
                            }
                        );
                        $router->group(
                            ['prefix' => 'settings', 'middleware' => [GridX\Http\Middleware\ThrottleRequests::class]],
                            function ($router) {
                                $router->get('branding', 'SettingController@getBrandingSettings');
                            }
                        );
                        $router->group(
                            ['prefix' => 'two-fa', 'middleware' => [GridX\Http\Middleware\ThrottleRequests::class]],
                            function ($router) {
                                $router->get('check', 'TwoFaController@checkTwoFactor');
                                $router->post('validate', 'TwoFaController@validateSession');
                                $router->post('verify', 'TwoFaController@verifyCode');
                                $router->post('resend', 'TwoFaController@resendCode');
                                $router->post('invalidate', 'TwoFaController@invalidateSession');
                            }
                        );
                        $router->group(
                            ['middleware' => ['gridx.protected']],
                            function ($router) {
                                $router->group(
                                    ['prefix' => 'lookup'],
                                    function ($router) {
                                        $router->post('refresh-blog-cache', 'LookupController@refreshBlogCache');
                                    }
                                );
                                $router->group(
                                    ['prefix' => 'auth'],
                                    function ($router) {
                                        $router->get('bootstrap', 'AuthController@bootstrap');
                                        $router->get('organizations', 'AuthController@getUserOrganizations');
                                        $router->post('change-user-password', 'AuthController@changeUserPassword');
                                        $router->post('impersonate', 'AuthController@impersonate');
                                        $router->delete('impersonate', 'AuthController@endImpersonation');
                                    }
                                );
                                $router->gridxRoutes(
                                    'api-credentials',
                                    function ($router, $controller) {
                                        $router->patch('roll/{id}', $controller('roll'));
                                        $router->get('export', $controller('export'));
                                    }
                                );
                                $router->get('iam/search', 'IamSearchController@search');
                                $router->get('developers/search', 'DeveloperSearchController@search');
                                $router->gridxRoutes('metrics', null, [], function ($router, $controller) {
                                    $router->get('iam', $controller('iam'));
                                    $router->get('iam/kpis', 'IamMetricsController@kpis');
                                    $router->get('iam/identity-health', 'IamMetricsController@identityHealth');
                                    $router->get('iam/access-coverage', 'IamMetricsController@accessCoverage');
                                    $router->get('iam/privileged-access', 'IamMetricsController@privilegedAccess');
                                    $router->get('iam/policy-surface', 'IamMetricsController@policySurface');
                                    $router->get('iam/group-coverage', 'IamMetricsController@groupCoverage');
                                    $router->get('iam/user-lifecycle', 'IamMetricsController@userLifecycle');
                                    $router->get('iam/users-by-type-created', 'IamMetricsController@usersByTypeCreated');
                                    $router->get('iam/activity', 'IamMetricsController@activity');
                                    $router->get('dev/kpis', 'DeveloperMetricsController@kpis');
                                    $router->get('dev/api-traffic', 'DeveloperMetricsController@apiTraffic');
                                    $router->get('dev/webhook-delivery', 'DeveloperMetricsController@webhookDelivery');
                                    $router->get('dev/credentials', 'DeveloperMetricsController@credentials');
                                    $router->get('dev/events', 'DeveloperMetricsController@events');
                                    $router->get('dev/endpoint-health', 'DeveloperMetricsController@endpointHealth');
                                    $router->get('dev/activity', 'DeveloperMetricsController@activity');
                                    $router->get('admin/kpis/{slug}', 'AdminMetricsController@kpi');
                                    $router->get('admin/widgets/{widget}', 'AdminMetricsController@widget');
                                    $router->get('admin/growth', 'AdminMetricsController@growth');
                                }
                                );
                                $router->gridxRoutes('settings', null, [], function ($router, $controller) {
                                    $router->get('overview', $controller('adminOverview'));
                                    $router->get('filesystem-config', $controller('getFilesystemConfig'));
                                    $router->post('filesystem-config', $controller('saveFilesystemConfig'));
                                    $router->post('test-filesystem-config', $controller('testFilesystemConfig'));
                                    $router->get('mail-config', $controller('getMailConfig'));
                                    $router->post('mail-config', $controller('saveMailConfig'));
                                    $router->post('test-mail-config', $controller('testMailConfig'));
                                    $router->get('queue-config', $controller('getQueueConfig'));
                                    $router->post('queue-config', $controller('saveQueueConfig'));
                                    $router->post('test-queue-config', $controller('testQueueConfig'));
                                    $router->get('services-config', $controller('getServicesConfig'));
                                    $router->post('services-config', $controller('saveServicesConfig'));
                                    $router->post('test-sms-provider-config', $controller('testSmsProviderConfig'));
                                    $router->post('test-twilio-config', $controller('testTwilioConfig'));
                                    $router->post('test-sentry-config', $controller('testSentryConfig'));
                                    $router->post('branding', $controller('saveBrandingSettings'));
                                    $router->put('branding', $controller('saveBrandingSettings'));
                                    $router->post('test-socket', $controller('testSocketcluster'));
                                    $router->get('notification-channels-config', $controller('getNotificationChannelsConfig'));
                                    $router->post('notification-channels-config', $controller('saveNotificationChannelsConfig'));
                                    $router->post('test-notification-channels-config', $controller('testNotificationChannelsConfig'));
                                }
                                );
                                $router->gridxRoutes('schedule-monitor', null, [], function ($router, $controller) {
                                    $router->get('tasks', $controller('tasks'));
                                    $router->get('{id}/logs', $controller('logs'));
                                }
                                );
                                $router->gridxRoutes('two-fa', null, [], function ($router, $controller) {
                                    $router->post('config', $controller('saveSystemConfig'));
                                    $router->get('config', $controller('getSystemConfig'));
                                    $router->get('enforce', $controller('shouldEnforce'));
                                }
                                );
                                $router->gridxRoutes('activities');
                                $router->gridxRoutes('api-events');
                                $router->gridxRoutes('api-request-logs');
                                $router->gridxRoutes(
                                    'webhook-endpoints',
                                    function ($router, $controller) {
                                        $router->patch('enable/{id}', $controller('enable'));
                                        $router->patch('disable/{id}', $controller('disable'));
                                        $router->get('events', $controller('events'));
                                        $router->get('versions', $controller('versions'));
                                    }
                                );
                                $router->gridxRoutes('webhook-request-logs');
                                $router->gridxRoutes('companies', null, [], function ($router, $controller) {
                                    $router->get('two-fa', $controller('getTwoFactorSettings'));
                                    $router->post('two-fa', $controller('saveTwoFactorSettings'));
                                    $router->post('transfer-ownership', $controller('transferOwnership'));
                                    $router->post('leave', $controller('leaveOrganization'));
                                    $router->match(['get', 'post'], 'export', $controller('export'));
                                    $router->get('{id}/extensions', $controller('extensions'));
                                    $router->patch('{id}/status', $controller('setAdminStatus'));
                                    $router->patch('{id}/onboarding', $controller('setAdminOnboarding'));
                                    $router->post('{id}/transfer-ownership', $controller('transferOwnershipAdmin'));
                                    $router->patch('{id}/users/{user}/activate', $controller('activateAdminUser'));
                                    $router->patch('{id}/users/{user}/deactivate', $controller('deactivateAdminUser'));
                                    $router->patch('{id}/users/{user}/verify', $controller('verifyAdminUser'));
                                    $router->delete('{id}/users/{user}', $controller('removeAdminUser'));
                                    $router->get('{id}/users', $controller('users'));
                                });
                                $router->gridxRoutes('users', null, [], function ($router, $controller) {
                                    $router->get('me', $controller('current'));
                                    $router->match(['get', 'post'], 'export', $controller('export'));
                                    $router->patch('deactivate/{id}', $controller('deactivate'));
                                    $router->patch('activate/{id}', $controller('activate'));
                                    $router->patch('verify/{id}', $controller('verify'));
                                    $router->delete('remove-from-company/{id}', $controller('removeFromCompany'));
                                    $router->post('invite-user', $controller('inviteUser'));
                                    $router->post('resend-invite', $controller('resendInvitation'));
                                    $router->post('set-password', $controller('setCurrentUserPassword'));
                                    $router->post('validate-password', $controller('validatePassword'));
                                    $router->post('change-password', $controller('changeUserPassword'));
                                    $router->post('two-fa', $controller('saveTwoFactorSettings'));
                                    $router->get('two-fa', $controller('getTwoFactorSettings'));
                                    $router->post('locale', $controller('setUserLocale'));
                                    $router->get('locale', $controller('getUserLocale'));
                                }
                                );
                                $router->gridxRoutes('user-devices');
                                $router->gridxRoutes('groups');
                                $router->gridxRoutes('roles');
                                $router->gridxRoutes('policies');
                                $router->gridxRoutes('permissions');
                                $router->gridxRoutes('extensions');
                                $router->gridxRoutes('categories');
                                $router->gridxRoutes('comments');
                                $router->gridxRoutes('custom-fields');
                                $router->gridxRoutes('custom-field-values');
                                $router->gridxRoutes('chat-channels', function ($router, $controller) {
                                    $router->get('available-participants', $controller('getAvailableParticipants'));
                                    $router->get('unread-count/{channelId}', $controller('getUnreadCountForChannel'));
                                    $router->get('unread-count', $controller('getUnreadCount'));
                                });
                                $router->gridxRoutes('chat-participants');
                                $router->gridxRoutes('chat-messages');
                                $router->gridxRoutes('chat-attachments');
                                $router->gridxRoutes('chat-receipts');
                                $router->gridxRoutes(
                                    'files',
                                    function ($router, $controller) {
                                        $router->get('download/{id?}', $controller('download'));
                                        $router->post('upload', $controller('upload'));
                                        $router->post('uploadBase64', $controller('upload-base64'));
                                    }
                                );
                                $router->gridxRoutes('transactions');
                                $router->gridxRoutes('notifications', function ($router, $controller) {
                                    $router->get('registry', $controller('registry'));
                                    $router->get('notifiables', $controller('notifiables'));
                                    $router->get('get-settings', $controller('getSettings'));
                                    $router->put('mark-as-read', $controller('markAsRead'));
                                    $router->put('mark-all-read', $controller('markAllAsRead'));
                                    $router->post('save-settings', $controller('saveSettings'));
                                });
                                $router->gridxRoutes('dashboards', function ($router, $controller) {
                                    $router->post('switch', $controller('switchDashboard'));
                                    $router->post('reset-default', $controller('resetDefaultDashboard'));
                                });
                                $router->gridxRoutes('dashboard-widgets');
                                $router->gridxRoutes('reports', function ($router, $controller) {
                                    $router->get('tables', $controller('getTables'));
                                    $router->get('tables/{table}/schema', $controller('getTableSchema'));
                                    $router->get('tables/{table}/columns', $controller('getTableColumns'));
                                    $router->get('tables/{table}/relationships', $controller('getTableRelationships'));
                                    $router->post('validate-query', $controller('validateQuery'));
                                    $router->post('validate-computed-column', $controller('validateComputedColumn'));
                                    $router->post('execute-query', $controller('executeQuery'));
                                    $router->post('analyze-query', $controller('analyzeQuery'));
                                    $router->post('export-query', $controller('exportQuery'));
                                    $router->get('query-recommendations', $controller('getQueryRecommendations'));
                                    $router->get('export-formats', $controller('getExportFormats'));
                                    $router->post('{id}/execute', $controller('execute'));
                                    $router->post('{id}/export', $controller('export'));
                                });
                                $router->gridxRoutes('schedules');
                                $router->gridxRoutes('schedule-items');
                                $router->gridxRoutes('schedule-templates', function ($router, $controller) {
                                    $router->post('{id}/apply', $controller('apply'));
                                    $router->post('{id}/materialize', $controller('materialize'));
                                });
                                $router->gridxRoutes('schedule-exceptions', function ($router, $controller) {
                                    $router->post('{id}/approve', $controller('approve'));
                                    $router->post('{id}/reject', $controller('reject'));
                                    $router->get('for-subject', $controller('forSubject'));
                                });
                                $router->gridxRoutes('schedule-availabilities');
                                $router->gridxRoutes('schedule-constraints');
                                $router->gridxRoutes('templates', function ($router, $controller) {
                                    $router->get('context-schemas', $controller('contextSchemas'));
                                    $router->post('preview', $controller('previewUnsaved'));
                                    $router->post('{id}/preview', $controller('preview'));
                                    $router->post('{id}/render', $controller('render'));
                                });
                                $router->gridxRoutes('template-queries');
                            }
                        );
                    }
                );
            }
        );
    }
);
