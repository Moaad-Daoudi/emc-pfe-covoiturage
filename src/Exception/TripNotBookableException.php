<?php

declare(strict_types=1);

namespace App\Exception;

final class TripNotBookableException extends DomainException
{
    public function __construct(string $message = 'Ce trajet n’est plus disponible à la réservation.')
    {
        parent::__construct($message);
    }
}
