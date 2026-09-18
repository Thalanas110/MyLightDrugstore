<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

final class ModuleDependencyScanner
{
    /**
     * @return list<string>
     */
    public static function findDomainViolations(string $source): array
    {
        return self::findViolations($source, [
            'Illuminate\\',
            'Symfony\\Component\\Http',
            'App\\Models\\',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function findApplicationPresentationViolations(string $source): array
    {
        return self::findViolations($source, [
            'Illuminate\\Database\\Eloquent\\',
            'Illuminate\\Database\\Query\\',
            'Illuminate\\Support\\Facades\\DB',
            'Illuminate\\Support\\Facades\\Schema',
            'App\\Models\\',
        ]);
    }

    /**
     * @param  list<string>  $forbiddenPrefixes
     * @return list<string>
     */
    private static function findViolations(string $source, array $forbiddenPrefixes): array
    {
        $violations = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                continue;
            }

            [$tokenType, $tokenValue] = $token;

            if (! in_array($tokenType, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $reference = ltrim($tokenValue, '\\');

            if (self::hasForbiddenPrefix($reference, $forbiddenPrefixes) || self::isModuleInfrastructure($reference)) {
                $violations[] = $reference;
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param  list<string>  $prefixes
     */
    private static function hasForbiddenPrefix(string $reference, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($reference, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function isModuleInfrastructure(string $reference): bool
    {
        $segments = explode('\\', $reference);

        return isset($segments[3])
            && $segments[0] === 'App'
            && $segments[1] === 'Modules'
            && $segments[3] === 'Infrastructure';
    }
}
