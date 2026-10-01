<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Persistence;

use App\SharedKernel\Infrastructure\Persistence\DeclareSchemasListener;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclareSchemasListener::class)]
final class DeclareSchemasListenerTest extends TestCase
{
    public function testDeclaresEverySchema(): void
    {
        $schema = new Schema();

        (new DeclareSchemasListener(['tow_request', 'messenger']))->postGenerateSchema($this->args($schema));

        self::assertTrue($schema->hasNamespace('tow_request'));
        self::assertTrue($schema->hasNamespace('messenger'));
    }

    public function testDoesNotFailWhenASchemaIsAlreadyDeclared(): void
    {
        $schema = new Schema();
        $schema->createNamespace('job');

        (new DeclareSchemasListener(['job']))->postGenerateSchema($this->args($schema));

        self::assertCount(1, array_filter($schema->getNamespaces(), static fn (string $name): bool => 'job' === $name));
    }

    public function testNoSchemasLeavesTheSchemaUntouched(): void
    {
        $schema = new Schema();

        (new DeclareSchemasListener([]))->postGenerateSchema($this->args($schema));

        self::assertSame([], $schema->getNamespaces());
    }

    private function args(Schema $schema): GenerateSchemaEventArgs
    {
        return new GenerateSchemaEventArgs(self::createStub(EntityManagerInterface::class), $schema);
    }
}
