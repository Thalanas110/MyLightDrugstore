<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\CreateMedicineCommand;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

final class MedicineCreateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'genericName' => ['required', 'string', 'min:1', 'max:160'],
            'brandName' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'dosageForm' => ['required', 'string', 'min:1', 'max:64'],
            'strength' => ['sometimes', 'nullable', 'string', 'max:64'],
            'unitPrice' => ['required', 'string', 'regex:/^(?=.*[1-9])\d{1,8}\.\d{2}$/D'],
            'storageLocation' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function toCommand(): CreateMedicineCommand
    {
        $genericName = $this->validated('genericName');
        $brandName = $this->validated('brandName');
        $description = $this->validated('description');
        $dosageForm = $this->validated('dosageForm');
        $strength = $this->validated('strength');
        $unitPrice = $this->validated('unitPrice');
        $storageLocation = $this->validated('storageLocation');

        if (! is_string($genericName)
            || ($brandName !== null && ! is_string($brandName))
            || ($description !== null && ! is_string($description))
            || ! is_string($dosageForm)
            || ($strength !== null && ! is_string($strength))
            || ! is_string($unitPrice)
            || ($storageLocation !== null && ! is_string($storageLocation))) {
            throw new LogicException('The validated medicine creation payload is invalid.');
        }

        return new CreateMedicineCommand(
            trim($genericName),
            $this->nullableTrimmed($brandName),
            $this->nullableTrimmed($description),
            trim($dosageForm),
            $this->nullableTrimmed($strength),
            $unitPrice,
            $this->nullableTrimmed($storageLocation),
        );
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
