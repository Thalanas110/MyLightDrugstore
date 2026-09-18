<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use LogicException;

final readonly class SaleDetails
{
    /**
     * @param  list<array{
     *     id: int,
     *     medicineId: int,
     *     quantity: int,
     *     unitPrice: string,
     *     lineTotal: string,
     *     state: string
     * }>  $items
     */
    public function __construct(
        public int $id,
        public string $createdAt,
        public string $state,
        public string $paymentStatus,
        public string $total,
        public array $items,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     createdAt: string,
     *     state: string,
     *     paymentStatus: string,
     *     total: string,
     *     items: list<array{
     *         id: int,
     *         medicineId: int,
     *         quantity: int,
     *         unitPrice: string,
     *         lineTotal: string,
     *         state: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt,
            'state' => $this->state,
            'paymentStatus' => $this->paymentStatus,
            'total' => $this->total,
            'items' => $this->items,
        ];
    }

    public static function fromArray(mixed $data): self
    {
        if (
            ! is_array($data)
            || ! is_int($data['id'] ?? null)
            || ! is_string($data['createdAt'] ?? null)
            || ! is_string($data['state'] ?? null)
            || ! is_string($data['paymentStatus'] ?? null)
            || ! is_string($data['total'] ?? null)
            || ! is_array($data['items'] ?? null)
            || ! array_is_list($data['items'])
        ) {
            throw new LogicException('The stored idempotent sale response is invalid.');
        }

        $items = [];

        foreach ($data['items'] as $item) {
            if (
                ! is_array($item)
                || ! is_int($item['id'] ?? null)
                || ! is_int($item['medicineId'] ?? null)
                || ! is_int($item['quantity'] ?? null)
                || ! is_string($item['unitPrice'] ?? null)
                || ! is_string($item['lineTotal'] ?? null)
                || ! is_string($item['state'] ?? null)
            ) {
                throw new LogicException('The stored idempotent sale item response is invalid.');
            }

            $items[] = [
                'id' => $item['id'],
                'medicineId' => $item['medicineId'],
                'quantity' => $item['quantity'],
                'unitPrice' => $item['unitPrice'],
                'lineTotal' => $item['lineTotal'],
                'state' => $item['state'],
            ];
        }

        return new self(
            $data['id'],
            $data['createdAt'],
            $data['state'],
            $data['paymentStatus'],
            $data['total'],
            $items,
        );
    }
}
