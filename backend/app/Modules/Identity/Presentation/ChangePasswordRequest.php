<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use Illuminate\Foundation\Http\FormRequest;
use LogicException;

final class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'max:256'],
            'newPassword' => ['required', 'string', 'min:12', 'max:256', 'different:currentPassword'],
        ];
    }

    public function currentPassword(): string
    {
        return $this->validatedString('currentPassword');
    }

    public function newPassword(): string
    {
        return $this->validatedString('newPassword');
    }

    private function validatedString(string $field): string
    {
        $value = $this->validated($field);

        if (! is_string($value)) {
            throw new LogicException('The validated password field must be a string.');
        }

        return $value;
    }
}
