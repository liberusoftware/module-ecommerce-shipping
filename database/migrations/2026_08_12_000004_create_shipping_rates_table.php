<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->foreignId('service_level_id')->constrained('shipping_service_levels')->cascadeOnDelete();
            $table->string('rate_type', 16);
            // Money is integer minor units. There is no decimal, float or double
            // column in this package.
            $table->integer('amount_minor')->nullable();
            $table->char('currency', 3);
            $table->string('band_axis', 20)->nullable();
            // Free shipping above a subtotal is a rate rule and lives here. Free
            // shipping from a coupon is ecommerce-promotions and does not.
            $table->integer('free_above_subtotal_minor')->nullable();
            // An estimate is an integer transit-day range plus its basis. Never
            // prose, never a date.
            $table->unsignedSmallInteger('transit_min_days');
            $table->unsignedSmallInteger('transit_max_days');
            $table->string('transit_basis', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'zone_id', 'service_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
