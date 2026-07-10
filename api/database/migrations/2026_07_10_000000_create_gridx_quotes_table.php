<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote -> approval -> payment -> dispatch pipeline. A Quote is the
 * pre-order booking/pricing stage; it only becomes a live dispatchable
 * Order once payment is confirmed (see QuoteController::dispatch).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('gridx_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_id')->unique();
            $table->uuid('company_uuid')->index();
            $table->uuid('customer_contact_uuid')->nullable();
            $table->uuid('order_uuid')->nullable()->comment('Set once dispatched — the live Order this quote became');

            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 6)->nullable();
            $table->decimal('pickup_lng', 10, 6)->nullable();
            $table->string('dropoff_address')->nullable();
            $table->decimal('dropoff_lat', 10, 6)->nullable();
            $table->decimal('dropoff_lng', 10, 6)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();

            $table->string('truck_type')->nullable();
            $table->decimal('cargo_weight_kg', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('distance_cost', 10, 2)->default(0);
            $table->decimal('fuel_surcharge', 10, 2)->default(0);
            $table->decimal('cross_border_surcharge', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->decimal('diesel_price_used', 6, 3)->nullable()->comment('Diesel price/liter used for the fuel surcharge calc, for audit');

            $table->string('status')->default('quote_pending')->comment('quote_pending, sent, approved, rejected, expired, sales_order, awaiting_payment, paid, dispatched');
            $table->string('pdf_path')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();

            $table->uuid('created_by_uuid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gridx_quotes');
    }
};
