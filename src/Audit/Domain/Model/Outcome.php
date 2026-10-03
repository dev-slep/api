<?php

declare(strict_types=1);

namespace App\Audit\Domain\Model;

/**
 * How a recorded thing ended: a command succeeded or failed; an event is only "recorded" (it already happened).
 */
enum Outcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Recorded = 'recorded';
}
