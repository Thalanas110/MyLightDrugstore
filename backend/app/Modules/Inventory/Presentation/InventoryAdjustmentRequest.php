<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\InventoryAdjustmentCommand;
use App\Modules\Inventory\Application\InventoryAdjustmentItem;
use App\Modules\Inventory\Domain\InventoryAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class InventoryAdjustmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(InventoryAdjustmentReason::class)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:lotId,quantityDelta'],
            'items.*.lotId' => ['required', 'integer', 'min:1', 'distinct', Rule::exists('inventory_lots', 'id')],
            'items.*.quantityDelta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
        ];
    }

    public function toCommand(int $actorUserId): InventoryAdjustmentCommand
    {
        $reason = $this->validated('reason');
        $items = $this->validated('items');

        if (! is_string($reason) || ! is_array($items)) {
            throw new LogicException('The validated inventory adjustment payload is invalid.');
        }

        $adjustmentItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new LogicException('A validated inventory adjustment item is invalid.');
            }

            $adjustmentItems[] = new InventoryAdjustmentItem(
                $this->parseValidatedInteger($item['lotId'] ?? null),
                $this->parseValidatedInteger($item['quantityDelta'] ?? null),
            );
        }

        return new InventoryAdjustmentCommand(
            $actorUserId,
            InventoryAdjustmentReason::from($reason),
            $adjustmentItems,
        );
    }

    private function parseValidatedInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
            return (int) $value;
        }

        throw new LogicException('A validated inventory adjustment number is invalid.');
    }
}
