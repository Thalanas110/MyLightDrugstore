<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\ReceiveStockCommand;
use App\Modules\Inventory\Application\ReceiveStockItem;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;
use Throwable;

final class ReceiveStockRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'receivedAt' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:medicineId,quantity,expiresAt'],
            'items.*.medicineId' => [
                'required',
                'integer',
                Rule::exists('medicines', 'id')->where('active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.expiresAt' => ['required', 'date_format:Y-m-d', $this->expiryDateIsNotBeforeReceipt()],
        ];
    }

    public function toCommand(int $actorUserId): ReceiveStockCommand
    {
        $receivedAt = $this->validated('receivedAt');
        $items = $this->validated('items');

        if (! is_string($receivedAt) || ! is_array($items)) {
            throw new LogicException('The validated stock receipt payload is invalid.');
        }

        $receiptItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new LogicException('A validated stock receipt item is invalid.');
            }

            $medicineId = $this->parseValidatedInteger($item['medicineId'] ?? null);
            $quantity = $this->parseValidatedInteger($item['quantity'] ?? null);
            $expiresAt = $item['expiresAt'] ?? null;

            if (! is_string($expiresAt)) {
                throw new LogicException('A validated stock expiry date is invalid.');
            }

            $expiryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt, new DateTimeZone('UTC'));

            if ($expiryDate === false) {
                throw new LogicException('A validated stock expiry date is invalid.');
            }

            $receiptItems[] = new ReceiveStockItem($medicineId, $quantity, $expiryDate);
        }

        return new ReceiveStockCommand(
            $actorUserId,
            (new DateTimeImmutable($receivedAt))->setTimezone(new DateTimeZone('UTC')),
            $receiptItems,
        );
    }

    private function expiryDateIsNotBeforeReceipt(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $receivedAt = $this->input('receivedAt');

            if (! is_string($receivedAt) || ! is_string($value)) {
                return;
            }

            try {
                $receivedDate = (new DateTimeImmutable($receivedAt))->format('Y-m-d');
            } catch (Throwable) {
                return;
            }

            if ($value < $receivedDate) {
                $fail('The expiry date must be on or after the receipt date.');
            }
        };
    }

    private function parseValidatedInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('A validated numeric stock field is invalid.');
    }
}
