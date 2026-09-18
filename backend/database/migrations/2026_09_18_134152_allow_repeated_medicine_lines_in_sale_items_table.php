<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', static function (Blueprint $table): void {
            $table->index('sale_id');
            $table->dropUnique('sale_items_sale_id_medicine_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', static function (Blueprint $table): void {
            $table->unique(['sale_id', 'medicine_id']);
            $table->dropIndex('sale_items_sale_id_index');
        });
    }
};
