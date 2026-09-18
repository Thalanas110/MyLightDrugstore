<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\CreateSaleCommand;
use App\Modules\Sales\Application\CreateSaleItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class CreateSaleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'idempotencyKey' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:medicineId,quantity'],
            'items.*.medicineId' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('medicines', 'id')->where('active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['items']) as $field) {
                $validator->errors()->add((string) $field, 'This field is not supported.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'idempotencyKey' => $this->header('Idempotency-Key'),
        ];
    }

    public function toCommand(int $actorUserId): CreateSaleCommand
    {
        $idempotencyKey = $this->validated('idempotencyKey');
        $items = $this->validated('items');

        if (! is_string($idempotencyKey) || ! is_array($items)) {
            throw new LogicException('The validated sale request is invalid.');
        }

        $saleItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new LogicException('A validated sale item is invalid.');
            }

            $saleItems[] = new CreateSaleItem(
                $this->parseValidatedInteger($item['medicineId'] ?? null),
                $this->parseValidatedInteger($item['quantity'] ?? null),
            );
        }

        return new CreateSaleCommand($actorUserId, $idempotencyKey, $saleItems);
    }

    private function parseValidatedInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('A validated sale integer is invalid.');
    }
}
