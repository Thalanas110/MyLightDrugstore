<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\MedicineListFilters;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class MedicineListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:160'],
            'active' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'lowStock' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'expiresBefore' => ['sometimes', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): MedicineListFilters
    {
        $query = $this->validated('q');
        $active = $this->validated('active');
        $lowStock = $this->validated('lowStock');
        $expiresBefore = $this->validated('expiresBefore');
        $page = $this->validated('page');
        $perPage = $this->validated('perPage');

        if (($query !== null && ! is_string($query)) || ($expiresBefore !== null && ! is_string($expiresBefore))) {
            throw new LogicException('The validated medicine list filters are invalid.');
        }

        $expiryDate = $expiresBefore === null
            ? null
            : DateTimeImmutable::createFromFormat('!Y-m-d', $expiresBefore, new DateTimeZone('UTC'));

        if ($expiryDate === false) {
            throw new LogicException('The validated medicine expiry filter is invalid.');
        }

        return new MedicineListFilters(
            $query === null || trim($query) === '' ? null : trim($query),
            $this->booleanFilter($active) ?? true,
            $this->booleanFilter($lowStock),
            $expiryDate,
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
            default => throw new LogicException('The validated medicine boolean filter is invalid.'),
        };
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

        throw new LogicException('The validated medicine pagination value is invalid.');
    }
}
