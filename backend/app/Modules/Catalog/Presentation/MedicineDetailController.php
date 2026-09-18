<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\GetMedicineAction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MedicineDetailController
{
    public function __invoke(string $medicineId, GetMedicineAction $getMedicine): JsonResponse
    {
        $parsedMedicineId = filter_var($medicineId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($parsedMedicineId)) {
            throw new NotFoundHttpException;
        }

        $medicine = $getMedicine->execute($parsedMedicineId);

        if ($medicine === null) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => (new MedicineListResource($medicine))->toArray(),
        ]);
    }
}
