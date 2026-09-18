<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Modules\Catalog\Infrastructure\Persistence\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MedicinePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_catalog_fields_and_a_two_decimal_unit_price(): void
    {
        $medicine = Medicine::factory()->create(['unit_price' => '12.5']);

        $this->assertSame('Example medicine', $medicine->generic_name);
        $this->assertSame('Example brand', $medicine->brand_name);
        $this->assertSame('tablet', $medicine->dosage_form);
        $this->assertSame('500 mg', $medicine->strength);
        $this->assertSame('12.50', $medicine->unit_price);
        $this->assertSame('A-03', $medicine->storage_location);
        $this->assertTrue($medicine->active);
    }

    public function test_a_new_medicine_is_active_by_default(): void
    {
        $medicine = Medicine::query()->create([
            'generic_name' => 'Example medicine',
            'brand_name' => null,
            'description' => null,
            'dosage_form' => 'tablet',
            'strength' => null,
            'unit_price' => '12.50',
            'storage_location' => null,
        ]);

        $this->assertTrue($medicine->refresh()->active);
    }
}
