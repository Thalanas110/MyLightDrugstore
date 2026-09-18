<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\SaleListFilters;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class SaleListRequest extends FormRequest
{
    private const array FILTER_FIELDS = [
        'paymentStatus',
        'state',
        'from',
        'to',
        'createdBy',
        'page',
        'perPage',
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $toRules = ['sometimes', 'date_format:Y-m-d'];

        if ($this->query->has('from')) {
            $toRules[] = 'after_or_equal:from';
        }

        return [
            'paymentStatus' => ['sometimes', Rule::in(['unpaid', 'paid'])],
            'state' => ['sometimes', Rule::in(['open', 'completed', 'cancelled'])],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => $toRules,
            'createdBy' => ['sometimes', 'integer', 'min:1', Rule::exists('users', 'id')],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query->all()), self::FILTER_FIELDS) as $field) {
                $validator->errors()->add((string) $field, 'This filter is not supported.');
            }
        }];
    }

    public function filters(): SaleListFilters
    {
        $validated = $this->validated();
        $paymentStatus = $validated['paymentStatus'] ?? null;
        $state = $validated['state'] ?? null;
        $from = $this->parseDate($validated['from'] ?? null);
        $to = $this->parseDate($validated['to'] ?? null);
        $createdBy = $validated['createdBy'] ?? null;
        $page = $validated['page'] ?? 1;
        $perPage = $validated['perPage'] ?? 25;

        if (
            ($paymentStatus !== null && ! is_string($paymentStatus))
            || ($state !== null && ! is_string($state))
            || ($createdBy !== null && ! is_int($createdBy) && ! (is_string($createdBy) && ctype_digit($createdBy)))
            || (! is_int($page) && ! (is_string($page) && ctype_digit($page)))
            || (! is_int($perPage) && ! (is_string($perPage) && ctype_digit($perPage)))
        ) {
            throw new LogicException('The validated sale list filters are invalid.');
        }

        return new SaleListFilters(
            $paymentStatus,
            $state,
            $from,
            $to,
            $createdBy === null ? null : (int) $createdBy,
            (int) $page,
            (int) $perPage,
        );
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new LogicException('A validated sale date filter is invalid.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false) {
            throw new LogicException('A validated sale date filter is invalid.');
        }

        return $date;
    }
}
