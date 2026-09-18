<?php

declare(strict_types=1);

namespace Tests\Architecture\Modules;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Architecture\Support\ModuleDependencyScanner;
use Tests\TestCase;

final class PresentationApplicationDependencyTest extends TestCase
{
    public function test_application_and_presentation_sources_do_not_access_persistence_or_infrastructure(): void
    {
        $violations = [];
        $moduleDirectories = glob(app_path('Modules').'/*', GLOB_ONLYDIR) ?: [];

        foreach ($moduleDirectories as $moduleDirectory) {
            foreach (['Application', 'Presentation'] as $layer) {
                $layerDirectory = $moduleDirectory.DIRECTORY_SEPARATOR.$layer;
                $sourceFiles = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($layerDirectory, FilesystemIterator::SKIP_DOTS),
                );

                foreach ($sourceFiles as $sourceFile) {
                    if (! $sourceFile->isFile() || $sourceFile->getExtension() !== 'php') {
                        continue;
                    }

                    $source = file_get_contents($sourceFile->getPathname());

                    if ($source === false) {
                        continue;
                    }

                    foreach (ModuleDependencyScanner::findApplicationPresentationViolations($source) as $dependency) {
                        $violations[] = $sourceFile->getPathname().': '.$dependency;
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_dependency_scanner_finds_persistence_and_infrastructure_references_in_sample_code(): void
    {
        $source = <<<'PHP'
            <?php

            namespace App\Modules\Sales\Application;

            use Illuminate\Database\Eloquent\Model;
            use Illuminate\Database\Query\Builder;
            use Illuminate\Support\Facades\DB;
            use App\Modules\Inventory\Infrastructure\StockRepository;
            PHP;

        $this->assertSame(
            [
                'Illuminate\Database\Eloquent\Model',
                'Illuminate\Database\Query\Builder',
                'Illuminate\Support\Facades\DB',
                'App\Modules\Inventory\Infrastructure\StockRepository',
            ],
            ModuleDependencyScanner::findApplicationPresentationViolations($source),
        );
    }
}
