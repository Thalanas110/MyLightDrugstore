<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_receipts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('received_at');
            $table->timestamps();
        });

        Schema::create('inventory_lots', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('inventory_receipts')->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->dateTime('received_at');
            $table->date('expires_at');
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_remaining');
            $table->timestamps();

            $table->index(['medicine_id', 'expires_at', 'quantity_remaining']);
        });

        Schema::create('inventory_movements', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('movement_type', 32);
            $table->integer('quantity_delta');
            $table->string('reason', 32)->nullable();
            $table->string('source_type', 48)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('occurred_at');

            $table->index(['movement_type', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('inventory_receipts');
    }
};
