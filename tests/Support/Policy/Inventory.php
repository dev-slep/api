<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy;

use function in_array;
use function is_array;

use ReflectionClass;
use ReflectionMethod;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Discovers, by reflection over the source tree, everything that requires a test (spec §12.2).
 */
final readonly class Inventory
{
    /**
     * @param list<class-string> $handlerInterfaces command/query handler markers: handlers are never exempt
     */
    public function __construct(
        private ClassFinder $source,
        private array $handlerInterfaces = [],
    ) {
    }

    /**
     * @return array{endpoints: list<string>, withoutMethods: list<string>}
     */
    public function routes(): array
    {
        $endpoints = [];
        $withoutMethods = [];

        foreach ($this->source->find() as $class => $path) {
            if (!str_contains($path, '/Infrastructure/Http/Controller/')) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $classRoute = $this->routeOf($reflection);
            $prefix = null === $classRoute ? '' : $this->pathOf($classRoute);
            $classMethods = $classRoute->methods ?? [];

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    $full = $prefix.$this->pathOf($route);
                    $httpMethods = [] !== $route->methods ? $route->methods : $classMethods;

                    if ([] === $httpMethods) {
                        $withoutMethods[] = sprintf('%s::%s (%s)', $class, $method->getName(), $full);

                        continue;
                    }
                    foreach ($httpMethods as $httpMethod) {
                        $endpoints[] = strtoupper($httpMethod).' '.$full;
                    }
                }
            }
        }

        return ['endpoints' => $endpoints, 'withoutMethods' => $withoutMethods];
    }

    /**
     * @return list<string> command names
     */
    public function consoleCommands(): array
    {
        $commands = [];
        foreach ($this->source->find() as $class => $path) {
            foreach ((new ReflectionClass($class))->getAttributes(AsCommand::class) as $attribute) {
                $commands[] = $attribute->newInstance()->name;
            }
        }

        return $commands;
    }

    /**
     * Methods of the interfaces in `<Module>/Contract/` (excluding Dto/ and Event/); SharedKernel is excluded.
     *
     * @return list<string> "Interface::method"
     */
    public function contractMethods(): array
    {
        $methods = [];
        foreach ($this->source->find() as $class => $path) {
            if (1 !== preg_match('#^(?!SharedKernel/)[^/]+/Contract/(?!Dto/|Event/)#', $path)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (!$reflection->isInterface()) {
                continue;
            }
            foreach ($reflection->getMethods() as $method) {
                $methods[] = $class.'::'.$method->getName();
            }
        }

        return $methods;
    }

    /**
     * Public methods declared in concrete classes, except exempt classes.
     *
     * @return array<string, list<string>> class => method names
     */
    public function publicMethods(): array
    {
        $result = [];
        foreach ($this->source->find() as $class => $path) {
            $reflection = new ReflectionClass($class);
            if ($reflection->isInterface() || $reflection->isAbstract() || $reflection->isTrait() || $this->isExempt($reflection, $path)) {
                continue;
            }

            $names = [];
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isAbstract() || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                if ($reflection->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
                    continue;
                }
                $names[] = $method->getName();
            }
            if ([] !== $names) {
                $result[$class] = $names;
            }
        }

        return $result;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function isExempt(ReflectionClass $class, string $path): bool
    {
        if ('Kernel.php' === $path) {
            return true;
        }

        foreach (['/Infrastructure/Persistence/Entity/', '/Infrastructure/Http/Controller/', '/Infrastructure/Http/Request/', '/Infrastructure/Http/Response/', '/Contract/Dto/'] as $segment) {
            if (str_contains($path, $segment)) {
                return true;
            }
        }

        // Command and query messages are exempt; their handlers are not
        if (1 === preg_match('#/Application/(Command|Query)/#', $path)) {
            foreach ($this->handlerInterfaces as $handlerInterface) {
                if ($class->implementsInterface($handlerInterface)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function pathOf(Route $route): string
    {
        $path = $route->path;

        return is_array($path) ? (array_values($path)[0] ?? '') : ($path ?? '');
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function routeOf(ReflectionClass $class): ?Route
    {
        $attributes = $class->getAttributes(Route::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
