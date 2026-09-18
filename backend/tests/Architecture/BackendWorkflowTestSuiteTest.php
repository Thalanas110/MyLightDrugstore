<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class BackendWorkflowTestSuiteTest extends TestCase
{
    private const array TEST_SUITES = [
        'Unit' => 'unit.xml',
        'Architecture' => 'architecture.xml',
        'FeatureFoundation' => 'feature-foundation.xml',
        'FeatureBusiness' => 'feature-business.xml',
        'FeatureTransport' => 'feature-transport.xml',
    ];

    public function test_ci_runs_each_bounded_suite_with_coverage_and_uploads_every_report(): void
    {
        $workflow = Yaml::parseFile(base_path('../.github/workflows/backend.yml'));
        $this->assertIsArray($workflow);
        $steps = $workflow['jobs']['tests']['steps'] ?? null;
        $this->assertIsArray($steps);
        $commands = [];

        foreach ($steps as $step) {
            if (! is_array($step) || ! is_string($step['run'] ?? null)) {
                continue;
            }

            foreach (self::TEST_SUITES as $suite => $report) {
                if (str_contains($step['run'], '--testsuite='.$suite)) {
                    $commands[$suite][] = $step['run'];
                }
            }
        }

        foreach (self::TEST_SUITES as $suite => $report) {
            $this->assertCount(1, $commands[$suite] ?? [], "The {$suite} suite must run exactly once in CI.");
            $command = $commands[$suite][0];
            $this->assertStringContainsString('timeout 110s php artisan test --compact', $command);
            $this->assertStringContainsString('--coverage-clover=storage/framework/cache/coverage/'.$report, $command);
        }

        $uploadSteps = array_filter($steps, static fn (mixed $step): bool => is_array($step)
            && ($step['uses'] ?? null) === 'actions/upload-artifact@v4'
            && str_contains((string) ($step['with']['path'] ?? ''), 'coverage/*.xml'));
        $this->assertCount(1, $uploadSteps, 'CI must upload all per-suite coverage reports.');
    }
}
