<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\ListInventoryLotsAction;
use Illuminate\Http\JsonResponse;

final class InventoryLotListController
{
    public function __invoke(InventoryLotListRequest $request, ListInventoryLotsAction $listInventoryLots): JsonResponse
    {
        $page = $listInventoryLots->execute($request->filters());

        return response()->json([
            'data' => array_map(
                static fn ($item): array => (new InventoryLotResource($item))->toArray(),
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
