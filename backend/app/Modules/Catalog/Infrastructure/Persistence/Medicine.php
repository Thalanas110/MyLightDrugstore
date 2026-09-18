<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'generic_name',
    'brand_name',
    'description',
    'dosage_form',
    'strength',
    'unit_price',
    'storage_location',
    'active',
])]
#[UseFactory(MedicineFactory::class)]
final class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
