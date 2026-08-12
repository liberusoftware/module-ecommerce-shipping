<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rate_bands', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreignId('rate_id')->constrained('shipping_rates')->cascadeOnDelete();
            // Half-open [lower_bound, upper_bound). The set must tile its axis
            // from zero with exactly one explicitly unbounded top band.
            $table->integer('lower_bound');
            $table->integer('upper_bound')->nullable();
            $table->boolean('is_unbounded')->default(false);
            $table->integer('amount_minor');
            $table->timestamps();

            $table->index(['rate_id', 'lower_bound']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rate_bands');
    }
};
