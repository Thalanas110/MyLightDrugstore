<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Catalog;

use App\Modules\Catalog\Presentation\Resources\MedicineCollection;
use App\Modules\Catalog\Presentation\Resources\MedicineResource;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;
use Tests\TestCase;

final class MedicineResourceTest extends TestCase
{
    public function test_single_medicine_response_uses_the_documented_data_envelope_and_camel_case_fields(): void
    {
        $request = Request::create('/api/v1/medicines/42');
        $resource = new MedicineResource($this->medicine());

        $response = $resource->toResponse($request);

        $this->assertSame([
            'data' => [
                'id' => 42,
                'genericName' => 'Example medicine',
                'brandName' => 'Example brand',
                'description' => 'Example description',
                'dosageForm' => 'tablet',
                'strength' => '10 mg',
                'unitPrice' => '12.50',
                'storageLocation' => 'A-03',
                'active' => true,
                'stockOnHand' => 80,
                'createdAt' => '2026-09-18T02:00:00Z',
                'updatedAt' => '2026-09-18T02:00:00Z',
            ],
        ], $response->getData(true));
    }

    public function test_paginated_medicines_use_the_documented_meta_shape_and_camel_case_fields(): void
    {
        $request = Request::create('/api/v1/medicines?page=2&perPage=25');
        $paginator = new LengthAwarePaginator(
            items: [$this->medicine()],
            total: 51,
            perPage: 25,
            currentPage: 2,
            options: ['path' => '/api/v1/medicines'],
        );
        $collection = new MedicineCollection($paginator);

        $response = $collection->toResponse($request);

        $this->assertSame([
            'data' => [
                [
                    'id' => 42,
                    'genericName' => 'Example medicine',
                    'brandName' => 'Example brand',
                    'description' => 'Example description',
                    'dosageForm' => 'tablet',
                    'strength' => '10 mg',
                    'unitPrice' => '12.50',
                    'storageLocation' => 'A-03',
                    'active' => true,
                    'stockOnHand' => 80,
                    'createdAt' => '2026-09-18T02:00:00Z',
                    'updatedAt' => '2026-09-18T02:00:00Z',
                ],
            ],
            'meta' => [
                'page' => 2,
                'perPage' => 25,
                'total' => 51,
            ],
        ], $response->getData(true));
    }

    private function medicine(): stdClass
    {
        $medicine = new stdClass;
        $medicine->id = 42;
        $medicine->generic_name = 'Example medicine';
        $medicine->brand_name = 'Example brand';
        $medicine->description = 'Example description';
        $medicine->dosage_form = 'tablet';
        $medicine->strength = '10 mg';
        $medicine->unit_price = '12.50';
        $medicine->storage_location = 'A-03';
        $medicine->active = true;
        $medicine->stock_on_hand = 80;
        $medicine->created_at = new DateTimeImmutable('2026-09-18T10:00:00+08:00');
        $medicine->updated_at = new DateTimeImmutable('2026-09-18T10:00:00+08:00');

        return $medicine;
    }
}
