<?php

declare(strict_types=1);

namespace App\Tests\Unit\Authentication\Infrastructure\Mail;

use App\Authentication\Application\Port\EmailDeliveryFailed;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OpaqueToken;
use App\Authentication\Infrastructure\Mail\SymfonyAuthEmailSender;
use App\Tests\Support\Authentication\RecordingMailer;

use function dirname;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(SymfonyAuthEmailSender::class)]
final class SymfonyAuthEmailSenderTest extends TestCase
{
    private const string TOKEN = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_';

    private RecordingMailer $mailer;

    private function sender(bool $failing = false): SymfonyAuthEmailSender
    {
        $mailer = $this->mailer = new RecordingMailer($failing);

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['en', 'sr_Latn'] as $locale) {
            $translator->addResource('yaml', dirname(__DIR__, 5)."/translations/auth_mail+intl-icu.$locale.yaml", $locale, 'auth_mail+intl-icu');
        }

        return new SymfonyAuthEmailSender($mailer, $translator, 'no-reply@slep.example', 'Slep', 'https://slep.example/verify-email?token={token}', 'https://slep.example/reset-password?token={token}');
    }

    public function testTheVerificationEmailCarriesTheLinkInTheRecipientsLanguage(): void
    {
        $this->sender()->sendEmailVerification(new Email('ana@example.com'), new Locale('en'), new OpaqueToken(self::TOKEN));

        self::assertCount(1, $this->mailer->sent);
        $mail = $this->mailer->sent[0];
        self::assertSame('ana@example.com', $mail->getTo()[0]->getAddress());
        self::assertSame('no-reply@slep.example', $mail->getFrom()[0]->getAddress());
        self::assertSame('Slep', $mail->getFrom()[0]->getName());
        self::assertSame('Confirm your email address', $mail->getSubject());
        self::assertStringContainsString('https://slep.example/verify-email?token='.self::TOKEN, (string) $mail->getTextBody());
        self::assertStringContainsString('href="https://slep.example/verify-email?token='.self::TOKEN.'"', (string) $mail->getHtmlBody());
    }

    public function testTheSerbianTemplateIsUsedForSerbianAccounts(): void
    {
        $this->sender()->sendEmailVerification(new Email('ana@example.com'), new Locale('sr_Latn'), new OpaqueToken(self::TOKEN));

        self::assertSame('Potvrdite svoju e-poštu', $this->mailer->sent[0]->getSubject());
        self::assertStringContainsString('Dobrodošli u Slep!', (string) $this->mailer->sent[0]->getTextBody());
    }

    public function testThePasswordResetEmailLinksToTheResetPage(): void
    {
        $this->sender()->sendPasswordReset(new Email('ana@example.com'), new Locale('en'), new OpaqueToken(self::TOKEN));

        self::assertSame('Reset your password', $this->mailer->sent[0]->getSubject());
        self::assertStringContainsString('https://slep.example/reset-password?token='.self::TOKEN, (string) $this->mailer->sent[0]->getTextBody());
        self::assertStringNotContainsString('verify-email', (string) $this->mailer->sent[0]->getTextBody());
    }

    public function testTheTokenIsUrlEncodedInTheLink(): void
    {
        $sender = new SymfonyAuthEmailSender(
            new class implements MailerInterface {
                public function send(RawMessage $message, ?Envelope $envelope = null): void
                {
                }
            },
            new Translator('en'),
            'a@b.c',
            'x',
            'https://x/{token}',
            'https://x/{token}',
        );

        $sender->sendEmailVerification(new Email('ana@example.com'), new Locale('en'), new OpaqueToken(self::TOKEN));

        $this->addToAssertionCount(1);
    }

    public function testTheTokenAppearsOnlyInTheLink(): void
    {
        $this->sender()->sendPasswordReset(new Email('ana@example.com'), new Locale('en'), new OpaqueToken(self::TOKEN));

        self::assertSame(1, substr_count((string) $this->mailer->sent[0]->getTextBody(), self::TOKEN));
        self::assertStringNotContainsString(self::TOKEN, (string) $this->mailer->sent[0]->getSubject());
    }

    public function testATransportFailureBecomesAnEmailDeliveryFailure(): void
    {
        $this->expectException(EmailDeliveryFailed::class);

        $this->sender(true)->sendPasswordReset(new Email('ana@example.com'), new Locale('en'), new OpaqueToken(self::TOKEN));
    }

    public function testTheTransportFailureIsKeptAsThePreviousException(): void
    {
        try {
            $this->sender(true)->sendEmailVerification(new Email('ana@example.com'), new Locale('en'), new OpaqueToken(self::TOKEN));
            self::fail('The failure was swallowed.');
        } catch (EmailDeliveryFailed $failure) {
            self::assertInstanceOf(TransportException::class, $failure->getPrevious());
            self::assertStringNotContainsString(self::TOKEN, $failure->getMessage());
        }
    }
}
