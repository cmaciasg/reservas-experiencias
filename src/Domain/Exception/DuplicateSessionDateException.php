<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when an experience already has a session scheduled on the given day.
 * Raised by the Application service after checking the repository (this is
 * not a Session constructor invariant: it needs to look at other sessions).
 */
final class DuplicateSessionDateException extends \DomainException
{
}
