<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // What the host called a "shipping method". Renamed because the host's
        // shipping_methods table is a different, incompatible shape and both may
        // sit in one database while a store migrates off it.
        Schema::create('shipping_service_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('code', 64);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_service_levels');
    }
};
