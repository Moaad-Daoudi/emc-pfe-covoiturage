<?php

declare(strict_types=1);

namespace App\Exception;

final class CancellationNotAllowedException extends DomainException
{
    public function __construct(string $message = 'Cette annulation n\'est pas autorisée.')
    {
        parent::__construct($message);
    }
}
