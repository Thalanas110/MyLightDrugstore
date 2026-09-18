<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ModuleClassScanner
{
    /**
     * @return list<string>
     */
    public static function classNames(): array
    {
        $classNames = [];
        $moduleRoot = app_path('Modules');
        $sourceFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($sourceFiles as $sourceFile) {
            if (! $sourceFile->isFile() || $sourceFile->getExtension() !== 'php' || $sourceFile->getFilename() === 'index.php') {
                continue;
            }

            $relativePath = substr($sourceFile->getPathname(), strlen($moduleRoot) + 1);
            $classPath = substr($relativePath, 0, -4);
            $className = 'App\\Modules\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $classPath);

            if (
                class_exists($className)
                || interface_exists($className)
                || enum_exists($className)
                || trait_exists($className)
            ) {
                $classNames[] = $className;
            }
        }

        return array_values(array_unique($classNames));
    }

    /**
     * @return list<string>
     */
    public static function applicationActionClassNames(): array
    {
        return array_values(array_filter(
            self::classNames(),
            static fn (string $className): bool => str_contains($className, '\\Application\\')
                && str_ends_with($className, 'Action'),
        ));
    }
}
