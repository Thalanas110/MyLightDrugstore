<?php

declare(strict_types=1);

namespace Tests\Architecture\Modules;

use ReflectionClass;
use ReflectionMethod;
use Tests\Architecture\Support\ModuleClassScanner;
use Tests\TestCase;

final class ModuleCodeShapeTest extends TestCase
{
    public function test_module_implementation_classes_are_final(): void
    {
        $classNames = ModuleClassScanner::classNames();

        $this->assertNotEmpty($classNames);

        foreach ($classNames as $className) {
            $reflection = new ReflectionClass($className);

            if ($reflection->isInterface() || $reflection->isEnum() || $reflection->isTrait()) {
                continue;
            }

            $this->assertTrue($reflection->isFinal(), $className.' must be final.');
        }
    }

    public function test_application_actions_expose_only_one_public_execute_operation(): void
    {
        $actionClasses = ModuleClassScanner::applicationActionClassNames();

        $this->assertNotEmpty($actionClasses);

        foreach ($actionClasses as $actionClass) {
            $reflection = new ReflectionClass($actionClass);
            $publicOperations = array_values(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $actionClass
                        && $method->getName() !== '__construct',
                ),
            ));

            $this->assertSame(['execute'], $publicOperations, $actionClass.' must expose only execute().');
        }
    }
}
