<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

final class ForbiddenDomainDependencyScanner
{
    /**
     * @return list<string>
     */
    public static function find(string $source): array
    {
        $forbiddenReferences = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                continue;
            }

            [$tokenType, $tokenValue] = $token;

            if (! in_array($tokenType, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $reference = ltrim($tokenValue, '\\');

            if (self::isForbidden($reference)) {
                $forbiddenReferences[] = $reference;
            }
        }

        return array_values(array_unique($forbiddenReferences));
    }

    private static function isForbidden(string $reference): bool
    {
        if (
            str_starts_with($reference, 'Illuminate\\')
            || str_starts_with($reference, 'Symfony\\Component\\Http')
            || str_starts_with($reference, 'App\\Models\\')
        ) {
            return true;
        }

        $segments = explode('\\', $reference);

        return isset($segments[3])
            && $segments[0] === 'App'
            && $segments[1] === 'Modules'
            && $segments[3] === 'Infrastructure';
    }
}
