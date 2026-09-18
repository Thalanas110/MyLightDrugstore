<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', static function (Blueprint $table): void {
            $table->id();
            $table->string('generic_name', 160);
            $table->string('brand_name', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('dosage_form', 64);
            $table->string('strength', 64)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->string('storage_location', 120)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'generic_name']);
            $table->index('brand_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
