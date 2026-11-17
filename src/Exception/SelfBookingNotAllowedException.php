<?php

declare(strict_types=1);

namespace App\Exception;

final class SelfBookingNotAllowedException extends DomainException
{
    public function __construct(string $message = 'Un conducteur ne peut pas réserver sur son propre trajet.')
    {
        parent::__construct($message);
    }
}
