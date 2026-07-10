<?php

namespace App\Providers;

use App\Http\Controllers\CopilotController;
use App\Http\Controllers\QuoteController;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes(
            function () {
                Route::get(
                    '/health',
                    function (Request $request) {
                        return response()->json(
                            [
                                'status' => 'ok',
                                'time' => microtime(true) - $request->attributes->get('request_start_time')
                            ]
                        );
                    }
                );

                // GridX Copilot: natural-language Q&A over live fleet data, called
                // by the console under the same auth/session middleware used by
                // every other internal console-facing request.
                Route::prefix('int/v1')->middleware(['fleetbase.protected'])->group(function () {
                    Route::post('copilot/ask', [CopilotController::class, 'ask']);

                    // Booking -> quote -> approval -> payment -> dispatch pipeline.
                    // Orders are never created directly from a booking request —
                    // only QuoteController::dispatch() creates one, gated on status=paid.
                    Route::prefix('quotes')->group(function () {
                        Route::get('/', [QuoteController::class, 'index']);
                        Route::post('/', [QuoteController::class, 'create']);
                        Route::get('{id}', [QuoteController::class, 'show']);
                        Route::put('{id}', [QuoteController::class, 'update']);
                        Route::get('{id}/pdf', [QuoteController::class, 'pdf']);
                        Route::post('{id}/send', [QuoteController::class, 'send']);
                        Route::post('{id}/approve', [QuoteController::class, 'approve']);
                        Route::post('{id}/reject', [QuoteController::class, 'reject']);
                        Route::post('{id}/mark-paid', [QuoteController::class, 'markPaid']);
                        Route::post('{id}/dispatch', [QuoteController::class, 'dispatchOrder']);
                        // Multi-warehouse consolidated planning: convert several paid
                        // quotes into unassigned Orders in one go, feeding them into
                        // the existing multi-vehicle Orchestrator for route planning.
                        Route::post('batch-dispatch', [QuoteController::class, 'batchDispatch']);
                    });

                    // Real road-following polyline for the Route Planning map,
                    // via self-hosted OSRM — separate from quote pricing distance.
                    Route::post('route-geometry', [QuoteController::class, 'routeGeometry']);
                });

                // Same quote endpoints, reachable via company API-key auth (fleetbase.api)
                // instead of a Sanctum user session — this is how the WhatsApp dispatch
                // agent creates quotes (it authenticates as the company, not a console user).
                Route::prefix('v1')->middleware(['fleetbase.api'])->group(function () {
                    Route::prefix('quotes')->group(function () {
                        Route::get('/', [QuoteController::class, 'index']);
                        Route::post('/', [QuoteController::class, 'create']);
                        Route::get('{id}', [QuoteController::class, 'show']);
                        Route::get('{id}/pdf', [QuoteController::class, 'pdf']);
                    });
                });
            }
        );
    }
}
