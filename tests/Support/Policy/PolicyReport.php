<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy;

use function count;
use function sprintf;

/**
 * Items that are missing a required test, grouped by category.
 */
final readonly class PolicyReport
{
    public const string ENDPOINTS = 'Endpoints without an integration test (#[CoversEndpoint])';
    public const string ROUTES_WITHOUT_METHODS = 'Routes without explicit "methods" (cannot be matched to a test)';
    public const string COMMANDS = 'Console commands without an integration test (#[CoversConsoleCommand])';
    public const string CONTRACT_METHODS = 'Contract methods without an application test (#[CoversContractMethod])';
    public const string PUBLIC_METHODS = 'Public methods without a unit test (#[CoversClass] / #[CoversMethod])';

    /**
     * @param array<string, list<string>> $missing category => items
     */
    public function __construct(private array $missing)
    {
    }

    public function isEmpty(): bool
    {
        return [] === $this->missing;
    }

    /**
     * @return list<string>
     */
    public function missing(string $category): array
    {
        return $this->missing[$category] ?? [];
    }

    public function format(): string
    {
        $lines = [];
        foreach ($this->missing as $category => $items) {
            $lines[] = sprintf('%s (%d):', $category, count($items));
            foreach ($items as $item) {
                $lines[] = '  - '.$item;
            }
        }

        return implode("\n", $lines);
    }
}
