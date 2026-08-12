<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreignId('zone_id')->nullable()->constrained('shipping_zones')->cascadeOnDelete();
            $table->foreignId('service_level_id')->nullable()->constrained('shipping_service_levels')->cascadeOnDelete();
            $table->string('restriction_type', 24);
            $table->integer('threshold')->nullable();
            // A restriction refuses with a recorded reason; it never silently
            // filters a service level out of the list.
            $table->string('reason_code', 64);
            $table->string('reason', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_restrictions');
    }
};
