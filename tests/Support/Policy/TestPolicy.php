<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy;

use App\Tests\Support\Attribute\CoversConsoleCommand;
use App\Tests\Support\Attribute\CoversContractMethod;
use App\Tests\Support\Attribute\CoversEndpoint;

use function in_array;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use ReflectionClass;

/**
 * Checks that every endpoint, console command, contract method and public method has its required test (spec §12.2).
 *
 * - endpoints and console commands: `tests/Integration` (`#[CoversEndpoint]`, `#[CoversConsoleCommand]`)
 * - contract methods: `tests/Application` (`#[CoversContractMethod]`)
 * - public methods: `tests/Unit` (`#[CoversClass]` covers all methods, `#[CoversMethod]` one method)
 */
final readonly class TestPolicy
{
    public function __construct(
        private Inventory $inventory,
        private ClassFinder $unitTests,
        private ClassFinder $applicationTests,
        private ClassFinder $integrationTests,
    ) {
    }

    public function check(): PolicyReport
    {
        $missing = [];

        $routes = $this->inventory->routes();
        $covered = $this->attributeKeys($this->integrationTests, CoversEndpoint::class, static fn (CoversEndpoint $a): string => $a->key());
        $this->addMissing($missing, PolicyReport::ENDPOINTS, array_diff($routes['endpoints'], $covered));
        $this->addMissing($missing, PolicyReport::ROUTES_WITHOUT_METHODS, $routes['withoutMethods']);

        $covered = $this->attributeKeys($this->integrationTests, CoversConsoleCommand::class, static fn (CoversConsoleCommand $a): string => $a->name);
        $this->addMissing($missing, PolicyReport::COMMANDS, array_diff($this->inventory->consoleCommands(), $covered));

        $covered = $this->attributeKeys($this->applicationTests, CoversContractMethod::class, static fn (CoversContractMethod $a): string => $a->key());
        $this->addMissing($missing, PolicyReport::CONTRACT_METHODS, array_diff($this->inventory->contractMethods(), $covered));

        $this->addMissing($missing, PolicyReport::PUBLIC_METHODS, $this->untestedPublicMethods());

        return new PolicyReport($missing);
    }

    /**
     * @return list<string>
     */
    private function untestedPublicMethods(): array
    {
        $coveredClasses = $this->attributeKeys($this->unitTests, CoversClass::class, static fn (CoversClass $a): string => $a->className());
        $coveredMethods = $this->attributeKeys($this->unitTests, CoversMethod::class, static fn (CoversMethod $a): string => $a->className().'::'.$a->methodName());

        $missing = [];
        foreach ($this->inventory->publicMethods() as $class => $methods) {
            if (in_array($class, $coveredClasses, true)) {
                continue;
            }
            foreach ($methods as $method) {
                if (!in_array($class.'::'.$method, $coveredMethods, true)) {
                    $missing[] = $class.'::'.$method;
                }
            }
        }

        return $missing;
    }

    /**
     * @template TAttribute of object
     *
     * @param class-string<TAttribute>     $attribute
     * @param callable(TAttribute): string $key
     *
     * @return list<string>
     */
    private function attributeKeys(ClassFinder $tests, string $attribute, callable $key): array
    {
        $keys = [];
        foreach (array_keys($tests->find()) as $class) {
            foreach ((new ReflectionClass($class))->getAttributes($attribute) as $reflected) {
                $keys[] = $key($reflected->newInstance());
            }
        }

        return $keys;
    }

    /**
     * @param array<string, list<string>> $missing
     * @param iterable<string>            $items
     */
    private function addMissing(array &$missing, string $category, iterable $items): void
    {
        $list = array_values(array_unique([...$items]));
        sort($list);
        if ([] !== $list) {
            $missing[$category] = $list;
        }
    }
}
