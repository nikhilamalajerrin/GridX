<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('gridx_quotes', function (Blueprint $table) {
            $table->uuid('agent_session_uuid')->nullable()->after('customer_contact_uuid');
            $table->text('agent_reasoning')->nullable()->after('notes')->comment('Why the agent priced/chose this the way it did, for dispatcher review');
        });
    }

    public function down(): void
    {
        Schema::table('gridx_quotes', function (Blueprint $table) {
            $table->dropColumn(['agent_session_uuid', 'agent_reasoning']);
        });
    }
};
