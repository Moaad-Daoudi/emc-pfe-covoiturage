<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Trip;
use App\Entity\User;

final readonly class ReviewTarget
{
    public function __construct(
        public Trip $trip,
        public User $target,
    ) {
    }
}
