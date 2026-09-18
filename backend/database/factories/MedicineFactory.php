<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
final class MedicineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generic_name' => 'Example medicine',
            'brand_name' => 'Example brand',
            'description' => 'An example medicine for tests.',
            'dosage_form' => 'tablet',
            'strength' => '500 mg',
            'unit_price' => '12.50',
            'storage_location' => 'A-03',
            'active' => true,
        ];
    }
}
