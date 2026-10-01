<?php

declare(strict_types=1);

namespace App\Tests\Support\Attribute;

use Attribute;

/**
 * Declares which contract (facade) method an application test covers.
 * Replaces PHPUnit's `#[CoversMethod]`, which does not accept interfaces as coverage targets.
 *
 * @template T of object
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class CoversContractMethod
{
    /**
     * @param class-string<T> $interface
     */
    public function __construct(public string $interface, public string $method)
    {
    }

    public function key(): string
    {
        return $this->interface.'::'.$this->method;
    }
}
