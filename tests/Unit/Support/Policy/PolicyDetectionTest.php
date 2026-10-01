<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\Policy;

use App\Tests\Support\Attribute\CoversConsoleCommand;
use App\Tests\Support\Attribute\CoversContractMethod;
use App\Tests\Support\Attribute\CoversEndpoint;
use App\Tests\Support\Policy\ClassFinder;
use App\Tests\Support\Policy\Fixtures\FixtureHandler;
use App\Tests\Support\Policy\Inventory;
use App\Tests\Support\Policy\PolicyReport;
use App\Tests\Support\Policy\TestPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(TestPolicy::class)]
#[CoversClass(Inventory::class)]
#[CoversClass(ClassFinder::class)]
#[CoversClass(PolicyReport::class)]
#[CoversClass(CoversEndpoint::class)]
#[CoversClass(CoversConsoleCommand::class)]
#[CoversClass(CoversContractMethod::class)]
final class PolicyDetectionTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../../Support/Policy/Fixtures';
    private const string NAMESPACE = 'App\Tests\Support\Policy\Fixtures\\';

    public function testEveryCategoryIsDetected(): void
    {
        $report = $this->policy()->check();
        $ns = self::NAMESPACE.'Src\Billing\\';

        self::assertSame(['PUT /api/v1/invoices/{id}/pay'], $report->missing(PolicyReport::ENDPOINTS));
        self::assertSame([$ns.'Infrastructure\Http\Controller\BrokenController::broken (/api/v1/broken)'], $report->missing(PolicyReport::ROUTES_WITHOUT_METHODS));
        self::assertSame(['billing:cleanup'], $report->missing(PolicyReport::COMMANDS));
        self::assertSame([$ns.'Contract\BillingApi::refund'], $report->missing(PolicyReport::CONTRACT_METHODS));
        self::assertSame(
            [
                $ns.'Application\Command\PayInvoiceHandler::__invoke',
                $ns.'Domain\Model\Ledger::reset',
                $ns.'Domain\Model\Status::label',
                $ns.'Infrastructure\Console\CleanupCommand::run',
                $ns.'Infrastructure\Console\SendInvoicesCommand::run',
            ],
            $report->missing(PolicyReport::PUBLIC_METHODS),
        );
        self::assertFalse($report->isEmpty());
    }

    public function testExemptClassesAreNotReported(): void
    {
        $formatted = $this->policy()->check()->format();

        self::assertStringNotContainsString('InvoiceRecord', $formatted, 'Doctrine records are exempt');
        self::assertStringNotContainsString('InvoiceDto', $formatted, 'DTOs are exempt');
        self::assertStringNotContainsString('PayInvoice::', $formatted, 'command messages are exempt');
        self::assertStringNotContainsString('InvoiceController::', $formatted, 'controllers are exempt from the unit rule');
        self::assertStringNotContainsString('Shape::', $formatted, 'abstract classes are not inventoried');
        self::assertStringNotContainsString('Invoice::hidden', $formatted, 'only public methods count');
        self::assertStringNotContainsString('Status::cases', $formatted, 'enum built-ins are ignored');
    }

    public function testFailureMessageGroupsItemsByCategory(): void
    {
        $formatted = $this->policy()->check()->format();

        self::assertStringContainsString(PolicyReport::CONTRACT_METHODS.' (1):', $formatted);
        self::assertStringContainsString('  - billing:cleanup', $formatted);
    }

    public function testNothingIsReportedWhenThereIsNothingToCheck(): void
    {
        $empty = new ClassFinder(self::FIXTURES.'/does-not-exist', 'Nope\\');
        $policy = new TestPolicy(new Inventory($empty), $empty, $empty, $empty);

        $report = $policy->check();

        self::assertTrue($report->isEmpty());
        self::assertSame('', $report->format());
    }

    public function testEndpointAttributeNormalisesTheMethod(): void
    {
        self::assertSame('GET /x', (new CoversEndpoint('get', '/x'))->key());
        self::assertSame('stdClass::bar', (new CoversContractMethod(stdClass::class, 'bar'))->key());
        self::assertSame('app:x', (new CoversConsoleCommand('app:x'))->name);
    }

    private function policy(): TestPolicy
    {
        $source = new ClassFinder(self::FIXTURES.'/Src', self::NAMESPACE.'Src\\');

        return new TestPolicy(
            new Inventory($source, [FixtureHandler::class]),
            new ClassFinder(self::FIXTURES.'/Tests/Unit', self::NAMESPACE.'Tests\Unit\\'),
            new ClassFinder(self::FIXTURES.'/Tests/Application', self::NAMESPACE.'Tests\Application\\'),
            new ClassFinder(self::FIXTURES.'/Tests/Integration', self::NAMESPACE.'Tests\Integration\\'),
        );
    }
}
