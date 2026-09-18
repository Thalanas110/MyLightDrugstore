<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

final class ValidateCsrfToken extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
