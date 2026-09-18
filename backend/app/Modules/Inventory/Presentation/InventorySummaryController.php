<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Application\ListInventoryAction;
use Illuminate\Http\JsonResponse;

final class InventorySummaryController
{
    public function __invoke(InventorySummaryRequest $request, ListInventoryAction $listInventory): JsonResponse
    {
        $page = $listInventory->execute($request->filters());

        return response()->json([
            'data' => array_map(
                static fn ($item): array => (new InventorySummaryResource($item))->toArray(),
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
