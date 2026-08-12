<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            // Opaque, so a shopper surface never has to name a row by an
            // enumerable integer.
            $table->string('reference', 64)->unique();

            // The discriminator. Stored, never inferred from whether a carrier
            // column happens to be null.
            $table->string('kind', 8);
            $table->string('status', 8);

            $table->integer('amount_minor');
            $table->char('currency', 3);

            $table->unsignedSmallInteger('transit_min_days')->nullable();
            $table->unsignedSmallInteger('transit_max_days')->nullable();
            $table->string('transit_basis', 16)->nullable();

            $table->string('service_level_code', 64);
            $table->string('service_level_name', 120);

            $table->char('destination_country', 2);
            $table->string('destination_subdivision', 8)->nullable();
            $table->string('destination_postcode', 16)->nullable();
            $table->integer('subtotal_minor')->nullable();
            $table->unsignedInteger('item_count')->nullable();

            // Derived provenance. Deliberately not a foreign key: a recorded
            // price is evidence and must outlive the rules it was derived from,
            // so deleting a zone must not cascade a price away. zone_code is a
            // snapshot for the same reason.
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('zone_code', 64)->nullable();
            $table->unsignedBigInteger('rate_id')->nullable();
            $table->unsignedBigInteger('rate_band_id')->nullable();
            $table->string('applied_rule', 20)->nullable();

            // Quoted provenance: a third party's answer, at an instant, about a
            // future physical movement. Irreproducible, so stored verbatim.
            $table->string('carrier_code', 64)->nullable();
            $table->string('carrier_service_code', 64)->nullable();
            $table->string('carrier_rate_reference', 128)->nullable();
            $table->timestamp('quoted_at')->nullable();

            $table->timestamp('expires_at');
            // A selected price is evidence: immutable, and never swept.
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_prices');
    }
};
