<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventoryMovementFilters;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class InventoryMovementListRequest extends FormRequest
{
    private const array MOVEMENT_TYPES = [
        'receipt',
        'sale',
        'sale_item_removal',
        'sale_cancellation',
        'adjustment',
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'medicineId' => ['sometimes', 'integer', 'min:1'],
            'lotId' => ['sometimes', 'integer', 'min:1'],
            'movementType' => ['sometimes', 'string', Rule::in(self::MOVEMENT_TYPES)],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): InventoryMovementFilters
    {
        $medicineId = $this->validated('medicineId');
        $lotId = $this->validated('lotId');
        $movementType = $this->validated('movementType');
        $from = $this->validated('from');
        $to = $this->validated('to');
        $page = $this->validated('page');
        $perPage = $this->validated('perPage');

        if (($movementType !== null && ! is_string($movementType))
            || ($from !== null && ! is_string($from))
            || ($to !== null && ! is_string($to))) {
            throw new LogicException('The validated inventory movement filters are invalid.');
        }

        return new InventoryMovementFilters(
            $this->optionalPositiveInteger($medicineId),
            $this->optionalPositiveInteger($lotId),
            $movementType,
            $this->parseDate($from),
            $this->parseDate($to),
            $this->positiveInteger($page, 1),
            $this->positiveInteger($perPage, 25),
        );
    }

    private function optionalPositiveInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new LogicException('A validated inventory movement identifier is invalid.');
    }

    private function positiveInteger(mixed $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new LogicException('A validated inventory movement pagination value is invalid.');
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false) {
            throw new LogicException('A validated inventory movement date is invalid.');
        }

        return $date;
    }
}
