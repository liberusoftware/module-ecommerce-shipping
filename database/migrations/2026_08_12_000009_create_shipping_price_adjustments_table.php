<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // A surcharge is its own recorded line with its own reason. It is never
        // folded into the price it modifies, and there is no pre-summed total
        // column anywhere in this package: the charge is a fold over these rows.
        Schema::create('shipping_price_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreignId('shipping_price_id')->constrained('shipping_prices')->cascadeOnDelete();
            $table->integer('amount_minor');
            $table->char('currency', 3);
            // Percentages are integer basis points: intdiv($base * $bps, 10_000).
            $table->integer('basis_points')->nullable();
            $table->string('reason_code', 64);
            $table->string('reason', 255);
            $table->timestamps();

            $table->index(['tenant_id', 'shipping_price_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_price_adjustments');
    }
};
