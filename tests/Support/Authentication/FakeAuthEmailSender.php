<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Application\Port\EmailDeliveryFailed;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OpaqueToken;

use function count;

use LogicException;

use function sprintf;

final class FakeAuthEmailSender implements AuthEmailSender
{
    /** @var list<array{kind: string, to: string, locale: string, token: string}> */
    public array $sent = [];
    public bool $failing = false;

    public function sendEmailVerification(Email $to, Locale $locale, OpaqueToken $token): void
    {
        $this->record('verification', $to, $locale, $token);
    }

    public function sendPasswordReset(Email $to, Locale $locale, OpaqueToken $token): void
    {
        $this->record('reset', $to, $locale, $token);
    }

    public function lastToken(string $kind): string
    {
        foreach (array_reverse($this->sent) as $mail) {
            if ($mail['kind'] === $kind) {
                return $mail['token'];
            }
        }

        throw new LogicException(sprintf('No "%s" email was sent.', $kind));
    }

    public function count(string $kind): int
    {
        return count(array_filter($this->sent, static fn (array $mail): bool => $mail['kind'] === $kind));
    }

    private function record(string $kind, Email $to, Locale $locale, OpaqueToken $token): void
    {
        if ($this->failing) {
            throw new EmailDeliveryFailed('The mail server is down.');
        }

        $this->sent[] = ['kind' => $kind, 'to' => $to->toString(), 'locale' => $locale->toString(), 'token' => $token->reveal()];
    }
}
