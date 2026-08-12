<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_price_parcels', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreignId('shipping_price_id')->constrained('shipping_prices')->cascadeOnDelete();
            // Integer grams and integer millimetres. One unit, at the boundary,
            // always: there is no weight_unit column in this package.
            $table->unsignedInteger('weight_grams');
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_price_parcels');
    }
};
