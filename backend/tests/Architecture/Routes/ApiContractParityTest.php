<?php

declare(strict_types=1);

namespace Tests\Architecture\Routes;

use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

final class ApiContractParityTest extends TestCase
{
    public function test_all_documented_endpoints_are_registered_with_the_documented_methods(): void
    {
        $documentedEndpoints = $this->documentedEndpoints();
        $registeredEndpoints = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $registeredEndpoints[] = $method.' '.$route->uri();
            }
        }

        sort($documentedEndpoints);
        sort($registeredEndpoints);

        $this->assertSame($documentedEndpoints, $registeredEndpoints);
    }

    public function test_every_legacy_mapping_targets_a_react_workflow_or_documented_api_endpoint(): void
    {
        $documentedEndpoints = $this->documentedEndpoints();
        $compatibilityRows = $this->compatibilityRows();

        $this->assertNotEmpty($compatibilityRows);

        foreach ($compatibilityRows as $row) {
            $this->assertNotSame('', $row['behavior'], $row['source'].' has no mapped behavior.');
            $this->assertNotSame('', $row['target'], $row['source'].' has no target workflow.');

            $apiTargets = $this->apiTargets($row['target']);
            $hasReactTarget = str_contains($row['target'], 'React');

            $this->assertTrue(
                $hasReactTarget || $apiTargets !== [],
                $row['source'].' must map to a React workflow or an API endpoint.',
            );

            foreach ($apiTargets as [$method, $path]) {
                $this->assertContains(
                    $method.' api/v1'.$path,
                    $documentedEndpoints,
                    $row['source'].' references an undocumented API endpoint.',
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function documentedEndpoints(): array
    {
        $document = $this->apiDocumentation();
        $inEndpoints = false;
        $endpoints = [];

        foreach (preg_split('/\R/', $document) ?: [] as $line) {
            if ($line === '## Endpoints') {
                $inEndpoints = true;

                continue;
            }

            if ($inEndpoints && str_starts_with($line, '## ')) {
                break;
            }

            if ($inEndpoints && preg_match('/^\|\s*`(GET|POST|PATCH|DELETE) (\/[^`]+)`\s*\|/', $line, $matches) === 1) {
                $endpoints[] = $matches[1].' api/v1'.$matches[2];
            }
        }

        return $endpoints;
    }

    /**
     * @return list<array{source: string, behavior: string, target: string}>
     */
    private function compatibilityRows(): array
    {
        $document = $this->apiDocumentation();
        $inMapping = false;
        $rows = [];

        foreach (preg_split('/\R/', $document) ?: [] as $line) {
            if ($line === '## Current PHP behavior and target mapping') {
                $inMapping = true;

                continue;
            }

            if ($inMapping && str_starts_with($line, 'The standalone')) {
                break;
            }

            if (! $inMapping || ! str_starts_with($line, '|')) {
                continue;
            }

            $columns = array_map('trim', explode('|', trim($line, '|')));

            if (count($columns) !== 3 || str_starts_with($columns[0], '---')) {
                continue;
            }

            $rows[] = [
                'source' => $columns[0],
                'behavior' => $columns[1],
                'target' => $columns[2],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function apiTargets(string $target): array
    {
        preg_match_all('/`((?:GET|POST|PATCH|DELETE)(?:\/(?:GET|POST|PATCH|DELETE))*\s+\/[^`]+)`/', $target, $matches);
        $apiTargets = [];

        foreach ($matches[1] as $match) {
            [$methods, $path] = explode(' ', $match, 2);
            $path = explode('?', $path, 2)[0];

            foreach (explode('/', $methods) as $method) {
                $apiTargets[] = [$method, $path];
            }
        }

        return $apiTargets;
    }

    private function apiDocumentation(): string
    {
        $document = file_get_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'API-DOCS.md');

        $this->assertIsString($document);

        return $document;
    }
}
