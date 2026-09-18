<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use Illuminate\Foundation\Http\FormRequest;
use LogicException;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:256'],
        ];
    }

    public function username(): string
    {
        $username = $this->validated('username');

        if (! is_string($username)) {
            throw new LogicException('The validated login username must be a string.');
        }

        return $username;
    }

    public function password(): string
    {
        $password = $this->validated('password');

        if (! is_string($password)) {
            throw new LogicException('The validated login password must be a string.');
        }

        return $password;
    }
}
