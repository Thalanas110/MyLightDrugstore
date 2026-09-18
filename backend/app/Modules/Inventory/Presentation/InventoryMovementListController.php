<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\ListInventoryMovementsAction;
use Illuminate\Http\JsonResponse;

final class InventoryMovementListController
{
    public function __invoke(
        InventoryMovementListRequest $request,
        ListInventoryMovementsAction $listInventoryMovements,
    ): JsonResponse {
        $page = $listInventoryMovements->execute($request->filters());

        return response()->json([
            'data' => array_map(
                static fn ($item): array => (new InventoryMovementResource($item))->toArray(),
                $page->items,
            ),
            'meta' => [
                'page' => $page->page,
                'perPage' => $page->perPage,
                'total' => $page->total,
            ],
        ]);
    }
}
