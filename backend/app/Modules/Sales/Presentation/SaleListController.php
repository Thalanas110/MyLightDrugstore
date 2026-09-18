<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation;

use App\Modules\Sales\Application\ListSalesAction;
use Illuminate\Http\JsonResponse;

final class SaleListController
{
    public function __invoke(SaleListRequest $request, ListSalesAction $listSales): JsonResponse
    {
        $page = $listSales->execute($request->filters());

        return response()->json([
            'data' => array_map(
                static fn ($item): array => (new SaleListResource($item))->toArray(),
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
