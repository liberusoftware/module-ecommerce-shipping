<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_territories', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            // A zone is a set of destination predicates, never a radius: this
            // module computes no distance and geocodes nothing.
            $table->char('country_code', 2);
            $table->string('subdivision_code', 8)->nullable();
            $table->string('postcode_prefix', 12)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_territories');
    }
};
