<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\AddSaleItemCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

final class AddSaleItemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'idempotencyKey' => ['required', 'string', 'max:255'],
            'medicineId' => ['required', 'integer', Rule::exists('medicines', 'id')->where('active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['medicineId', 'quantity']) as $field) {
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

    public function toCommand(int $actorUserId, int $saleId): AddSaleItemCommand
    {
        $idempotencyKey = $this->validated('idempotencyKey');

        if (! is_string($idempotencyKey)) {
            throw new LogicException('The validated sale item request is invalid.');
        }

        return new AddSaleItemCommand(
            $actorUserId,
            $saleId,
            $idempotencyKey,
            $this->parseValidatedInteger($this->validated('medicineId')),
            $this->parseValidatedInteger($this->validated('quantity')),
        );
    }

    private function parseValidatedInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('A validated sale item integer is invalid.');
    }
}
