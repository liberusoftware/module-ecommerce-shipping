<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('code', 64);
            $table->string('name', 120);
            // Higher wins. Two zones that could match the same destination at the
            // same precedence are refused when the second is saved (SaveZone),
            // because ordering resolved at read time is ordering nobody can audit.
            $table->integer('precedence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'precedence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
