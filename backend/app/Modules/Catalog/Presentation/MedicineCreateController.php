<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\CreateMedicineAction;
use App\Modules\Catalog\Application\GetMedicineAction;
use Illuminate\Http\JsonResponse;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class MedicineCreateController
{
    public function __invoke(
        MedicineCreateRequest $request,
        CreateMedicineAction $createMedicine,
        GetMedicineAction $getMedicine,
    ): JsonResponse {
        $medicineId = $createMedicine->execute($request->toCommand());
        $medicine = $getMedicine->execute($medicineId);

        if ($medicine === null) {
            throw new LogicException('The created medicine could not be reloaded.');
        }

        return response()->json([
            'data' => (new MedicineListResource($medicine))->toArray(),
        ], Response::HTTP_CREATED);
    }
}
