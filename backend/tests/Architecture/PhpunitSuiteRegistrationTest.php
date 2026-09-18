<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;
use Tests\TestCase;

final class PhpunitSuiteRegistrationTest extends TestCase
{
    private const array CI_SUITES = [
        'Unit',
        'Architecture',
        'FeatureFoundation',
        'FeatureBusiness',
        'FeatureTransport',
    ];

    public function test_ci_suites_cover_every_test_file_exactly_once(): void
    {
        $xml = simplexml_load_file(base_path('phpunit.xml'));
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        $suiteNodes = [];

        foreach ($xml->testsuites->testsuite as $suiteNode) {
            $name = (string) $suiteNode['name'];

            if (in_array($name, self::CI_SUITES, true)) {
                $suiteNodes[$name] = $suiteNode;
            }
        }

        $this->assertSame(self::CI_SUITES, array_keys($suiteNodes));

        $registeredPaths = [];

        foreach ($suiteNodes as $suiteNode) {
            $excludedDirectories = [];

            foreach ($suiteNode->exclude as $excludeNode) {
                $excludePath = realpath(base_path((string) $excludeNode));

                if ($excludePath !== false) {
                    $excludedDirectories[] = $excludePath;
                }
            }

            foreach ($suiteNode->directory as $directoryNode) {
                $directory = realpath(base_path((string) $directoryNode));
                $suffix = (string) ($directoryNode['suffix'] ?? 'Test.php');

                $this->assertNotFalse($directory, 'Every PHPUnit suite directory must exist.');

                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
                    if (! $file->isFile() || ! str_ends_with($file->getFilename(), $suffix)) {
                        continue;
                    }

                    $path = $file->getRealPath();

                    if ($path === false || $this->isExcluded($path, $excludedDirectories)) {
                        continue;
                    }

                    $registeredPaths[] = $this->relativeTestPath($path);
                }
            }

            foreach ($suiteNode->file as $fileNode) {
                $path = realpath(base_path((string) $fileNode));

                $this->assertNotFalse($path, 'Every PHPUnit suite file must exist.');
                $registeredPaths[] = $this->relativeTestPath($path);
            }
        }

        $expectedPaths = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
                $path = $file->getRealPath();

                if ($path !== false) {
                    $expectedPaths[] = $this->relativeTestPath($path);
                }
            }
        }

        $registeredCounts = array_count_values($registeredPaths);
        $duplicates = array_keys(array_filter($registeredCounts, static fn (int $count): bool => $count > 1));
        $registeredUnique = array_keys($registeredCounts);
        sort($expectedPaths);
        sort($registeredUnique);
        sort($duplicates);

        $this->assertSame([], $duplicates, 'A test file must belong to only one CI suite.');
        $this->assertSame($expectedPaths, $registeredUnique, 'The CI suites must include every test file.');
    }

    /** @param list<string> $excludedDirectories */
    private function isExcluded(string $path, array $excludedDirectories): bool
    {
        foreach ($excludedDirectories as $excludedDirectory) {
            if ($path === $excludedDirectory || str_starts_with($path, $excludedDirectory.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function relativeTestPath(string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(base_path()) + 1));
    }
}
