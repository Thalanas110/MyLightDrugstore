<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

final class StrictInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
