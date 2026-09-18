<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

use InvalidArgumentException;

final class RequestAdditionalData
{
    public static function fromRequestTarget(string $method, string $requestTarget): string
    {
        $normalizedMethod = strtoupper($method);

        if (preg_match('/\A[A-Z]+\z/', $normalizedMethod) !== 1) {
            throw new InvalidArgumentException('The HTTP method is invalid.');
        }

        if ($requestTarget === '' || preg_match('/[\x00-\x20\x7F]/', $requestTarget) === 1) {
            throw new InvalidArgumentException('The request target is invalid.');
        }

        $parts = parse_url($requestTarget);

        if ($parts === false) {
            throw new InvalidArgumentException('The request target is invalid.');
        }

        $hasScheme = isset($parts['scheme']);
        $hasHost = isset($parts['host']);

        if (($hasScheme && (! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || ! $hasHost))
            || ($hasHost && ! $hasScheme)) {
            throw new InvalidArgumentException('The request target is invalid.');
        }

        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        } elseif (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $normalizedMethod.' '.$path;
    }
}
