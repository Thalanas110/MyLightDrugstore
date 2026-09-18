<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation;

use App\Modules\Catalog\Application\UpdateMedicineCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

final class MedicineUpdateRequest extends FormRequest
{
    private const array EDITABLE_FIELDS = [
        'genericName',
        'brandName',
        'description',
        'dosageForm',
        'strength',
        'unitPrice',
        'storageLocation',
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'genericName' => ['sometimes', 'required', 'string', 'min:1', 'max:160'],
            'brandName' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'dosageForm' => ['sometimes', 'required', 'string', 'min:1', 'max:64'],
            'strength' => ['sometimes', 'nullable', 'string', 'max:64'],
            'unitPrice' => ['sometimes', 'required', 'string', 'regex:/^(?=.*[1-9])\d{1,8}\.\d{2}$/D'],
            'storageLocation' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_intersect(self::EDITABLE_FIELDS, array_keys($this->all())) === []) {
                $validator->errors()->add('medicine', 'At least one catalog field must be provided.');
            }
        }];
    }

    public function toCommand(int $medicineId): UpdateMedicineCommand
    {
        $validated = $this->validated();
        $changes = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $validated[$field];

            if ($value !== null && ! is_string($value)) {
                throw new LogicException('A validated medicine update field is invalid.');
            }

            $changes[$field] = $value === null || $field === 'unitPrice' ? $value : trim($value);
        }

        return new UpdateMedicineCommand($medicineId, $changes);
    }
}
