<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventoryLotFilters;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class InventoryLotListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'medicineId' => ['sometimes', 'integer', 'min:1'],
            'expiresBefore' => ['sometimes', 'date_format:Y-m-d'],
            'available' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): InventoryLotFilters
    {
        $medicineId = $this->validated('medicineId');
        $expiresBefore = $this->validated('expiresBefore');
        $available = $this->validated('available');
        $page = $this->validated('page');
        $perPage = $this->validated('perPage');

        if ($expiresBefore !== null && ! is_string($expiresBefore)) {
            throw new LogicException('The validated lot expiry filter is invalid.');
        }

        $expiryDate = $expiresBefore === null
            ? null
            : DateTimeImmutable::createFromFormat('!Y-m-d', $expiresBefore, new DateTimeZone('UTC'));

        if ($expiryDate === false) {
            throw new LogicException('The validated lot expiry filter is invalid.');
        }

        return new InventoryLotFilters(
            $this->nullablePositiveInteger($medicineId),
            $expiryDate,
            $this->booleanFilter($available),
            $this->positiveInteger($page, 1),
            $this->positiveInteger($perPage, 25),
        );
    }

    private function booleanFilter(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            null => null,
            default => throw new LogicException('The validated lot availability filter is invalid.'),
        };
    }

    private function nullablePositiveInteger(mixed $value): ?int
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

        throw new LogicException('The validated lot pagination value is invalid.');
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

        throw new LogicException('The validated lot pagination value is invalid.');
    }
}
