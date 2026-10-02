<?php

declare(strict_types=1);

namespace App\Authentication\Infrastructure\Mail;

use App\Authentication\Application\Port\AuthEmailSender;
use App\Authentication\Application\Port\EmailDeliveryFailed;
use App\Authentication\Domain\Model\Email;
use App\Authentication\Domain\Model\Locale;
use App\Authentication\Domain\Model\OpaqueToken;

use const ENT_QUOTES;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as MimeEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends the verification and password reset emails with Symfony Mailer. Texts come from the `auth_mail`
 * translation domain, in the recipient's language. The raw token only appears in the link in the body.
 */
final readonly class SymfonyAuthEmailSender implements AuthEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private string $fromAddress,
        private string $fromName,
        private string $verificationUrlTemplate,
        private string $resetUrlTemplate,
    ) {
    }

    public function sendEmailVerification(Email $to, Locale $locale, OpaqueToken $token): void
    {
        $this->send($to, $locale, 'email_verification', str_replace('{token}', rawurlencode($token->reveal()), $this->verificationUrlTemplate));
    }

    public function sendPasswordReset(Email $to, Locale $locale, OpaqueToken $token): void
    {
        $this->send($to, $locale, 'password_reset', str_replace('{token}', rawurlencode($token->reveal()), $this->resetUrlTemplate));
    }

    private function send(Email $to, Locale $locale, string $template, string $link): void
    {
        $text = $this->translator->trans($template.'.text', ['link' => $link], 'auth_mail', $locale->toString());
        $html = $this->translator->trans($template.'.html', ['link' => htmlspecialchars($link, ENT_QUOTES)], 'auth_mail', $locale->toString());

        $message = (new MimeEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to->toString())
            ->subject($this->translator->trans($template.'.subject', [], 'auth_mail', $locale->toString()))
            ->text($text)
            ->html($html);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $failure) {
            throw new EmailDeliveryFailed('The authentication email could not be sent.', 0, $failure);
        }
    }
}
