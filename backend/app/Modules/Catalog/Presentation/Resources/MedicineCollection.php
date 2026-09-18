<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

#[Collects(MedicineResource::class)]
final class MedicineCollection extends ResourceCollection
{
    /**
     * @param  array{current_page: int, per_page: int, total: int, ...<string, mixed>}  $paginated
     * @param  array<string, mixed>  $default
     * @return array{meta: array{page: int, perPage: int, total: int}}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'page' => $paginated['current_page'],
                'perPage' => $paginated['per_page'],
                'total' => $paginated['total'],
            ],
        ];
    }
}
