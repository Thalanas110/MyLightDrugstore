<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 16);
            $table->string('payment_status', 16);
            $table->decimal('total', 17, 2);
            $table->timestamps();

            $table->index(['state', 'payment_status', 'created_at']);
            $table->index(['created_by_user_id', 'created_at']);
        });

        Schema::create('sale_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 15, 2);
            $table->string('state', 16)->default('active');
            $table->timestamps();

            $table->unique(['sale_id', 'medicine_id']);
        });

        Schema::create('sales_idempotency_keys', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 32);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->unique(['actor_user_id', 'operation', 'idempotency_key_hash'], 'sales_idempotency_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_idempotency_keys');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
