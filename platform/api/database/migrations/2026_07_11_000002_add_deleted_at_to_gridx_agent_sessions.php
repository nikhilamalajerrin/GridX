<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleetbase's base Model class assumes SoftDeletes (queries filter on
 * deleted_at) — same gap hit and fixed for gridx_quotes.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('gridx_agent_sessions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('gridx_agent_sessions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
