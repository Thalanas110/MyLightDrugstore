<?php

declare(strict_types=1);

namespace Tests\Architecture\Modules;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Architecture\Support\ModuleDependencyScanner;
use Tests\TestCase;

final class ModuleDomainDependencyTest extends TestCase
{
    public function test_domain_sources_reject_framework_and_module_infrastructure_dependencies(): void
    {
        $violations = [];
        $moduleDirectories = glob(app_path('Modules').'/*', GLOB_ONLYDIR) ?: [];

        foreach ($moduleDirectories as $moduleDirectory) {
            $domainDirectory = $moduleDirectory.DIRECTORY_SEPARATOR.'Domain';
            $domainFiles = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($domainDirectory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($domainFiles as $domainFile) {
                if (! $domainFile->isFile() || $domainFile->getExtension() !== 'php') {
                    continue;
                }

                $source = file_get_contents($domainFile->getPathname());

                if ($source === false) {
                    continue;
                }

                foreach (ModuleDependencyScanner::findDomainViolations($source) as $dependency) {
                    $violations[] = $domainFile->getPathname().': '.$dependency;
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_dependency_scanner_finds_forbidden_domain_references_in_sample_code(): void
    {
        $source = <<<'PHP'
            <?php

            namespace App\Modules\Sales\Domain;

            use Illuminate\Database\Eloquent\Model;
            use Symfony\Component\HttpFoundation\Request;
            use App\Modules\Inventory\Infrastructure\StockRepository;
            PHP;

        $this->assertSame(
            [
                'Illuminate\Database\Eloquent\Model',
                'Symfony\Component\HttpFoundation\Request',
                'App\Modules\Inventory\Infrastructure\StockRepository',
            ],
            ModuleDependencyScanner::findDomainViolations($source),
        );
    }
}
