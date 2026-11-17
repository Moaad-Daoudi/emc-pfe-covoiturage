<?php

declare(strict_types=1);

namespace App\Exception;

final class ReviewNotAllowedException extends DomainException
{
    public function __construct(string $message = 'La soumission d\'un avis n\'est pas autorisée.')
    {
        parent::__construct($message);
    }
}
