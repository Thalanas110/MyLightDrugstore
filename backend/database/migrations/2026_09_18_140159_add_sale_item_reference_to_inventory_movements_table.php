<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', static function (Blueprint $table): void {
            $table->foreignId('sale_item_id')
                ->nullable()
                ->constrained('sale_items')
                ->restrictOnDelete();
            $table->index(['sale_item_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', static function (Blueprint $table): void {
            $table->dropForeign(['sale_item_id']);
            $table->dropIndex(['sale_item_id', 'movement_type']);
            $table->dropColumn('sale_item_id');
        });
    }
};
