<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Resources;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $generic_name
 * @property-read string|null $brand_name
 * @property-read string|null $description
 * @property-read string $dosage_form
 * @property-read string|null $strength
 * @property-read string $unit_price
 * @property-read string|null $storage_location
 * @property-read bool $active
 * @property-read int $stock_on_hand
 * @property-read DateTimeInterface|null $created_at
 * @property-read DateTimeInterface|null $updated_at
 */
final class MedicineResource extends JsonResource
{
    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'genericName' => $this->generic_name,
            'brandName' => $this->brand_name,
            'description' => $this->description,
            'dosageForm' => $this->dosage_form,
            'strength' => $this->strength,
            'unitPrice' => $this->unit_price,
            'storageLocation' => $this->storage_location,
            'active' => $this->active,
            'stockOnHand' => $this->stock_on_hand,
            'createdAt' => $this->formatUtc($this->created_at),
            'updatedAt' => $this->formatUtc($this->updated_at),
        ];
    }

    private function formatUtc(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
