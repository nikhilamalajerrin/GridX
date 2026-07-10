<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleetbase\Models\Model (the base class GridxQuote extends) always uses
 * SoftDeletes, so every query includes a deleted_at IS NULL check —
 * this column was missing from the initial migration.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('gridx_quotes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('gridx_quotes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
