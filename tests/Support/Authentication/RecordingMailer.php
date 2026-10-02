<?php

declare(strict_types=1);

namespace App\Tests\Support\Authentication;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * A mailer for unit tests: keeps the emails it was asked to send, or fails like a mail server that is down.
 */
final class RecordingMailer implements MailerInterface
{
    /** @var list<Email> */
    public array $sent = [];

    public function __construct(private readonly bool $failing = false)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->failing) {
            throw new TransportException('SMTP is down');
        }
        if ($message instanceof Email) {
            $this->sent[] = $message;
        }
    }
}
