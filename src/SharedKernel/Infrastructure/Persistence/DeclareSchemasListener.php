<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Persistence;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Declares the module schemas in the desired schema, so that schema diffs
 * never try to drop schemas that are created by the per-module migrations.
 */
final readonly class DeclareSchemasListener
{
    /**
     * @param list<string> $schemas
     */
    public function __construct(private array $schemas)
    {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        foreach ($this->schemas as $name) {
            if (!$schema->hasNamespace($name)) {
                $schema->createNamespace($name);
            }
        }
    }
}
