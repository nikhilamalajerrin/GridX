<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per AI agent run (one inbound WhatsApp/email message handled
 * end-to-end). Lets the dispatcher review what the agent decided and why,
 * instead of only seeing the resulting quote with no context.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('gridx_agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid')->index();

            $table->string('mode')->default('suggestions')->comment('suggestions (human-in-the-loop) or autonomous');
            $table->string('status')->default('running')->comment('running, completed, failed');
            $table->string('channel')->nullable()->comment('whatsapp, email, simulate');
            $table->string('sender')->nullable();
            $table->text('summary')->nullable()->comment('Agent\'s final reply / outcome summary for this session');

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gridx_agent_sessions');
    }
};
