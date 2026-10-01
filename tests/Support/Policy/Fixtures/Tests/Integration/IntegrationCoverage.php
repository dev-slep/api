<?php

declare(strict_types=1);

namespace App\Tests\Support\Policy\Fixtures\Tests\Integration;

use App\Tests\Support\Attribute\CoversConsoleCommand;
use App\Tests\Support\Attribute\CoversEndpoint;

#[CoversEndpoint('get', '/api/v1/invoices')]
#[CoversEndpoint('POST', '/api/v1/invoices/{id}/pay')]
#[CoversConsoleCommand('billing:send')]
final class IntegrationCoverage
{
}
