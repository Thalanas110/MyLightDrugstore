<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\ListMedicinesAction;
use Illuminate\Http\JsonResponse;

final class MedicineListController
{
    public function __invoke(MedicineListRequest $request, ListMedicinesAction $listMedicines): JsonResponse
    {
        $page = $listMedicines->execute($request->filters());

        return response()->json([
            'data' => array_map(
                static fn ($item): array => (new MedicineListResource($item))->toArray(),
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
