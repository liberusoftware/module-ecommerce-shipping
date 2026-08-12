<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('operation', 64);
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64);
            $table->string('state', 16);
            $table->string('result_reference', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'operation', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_idempotency_keys');
    }
};
