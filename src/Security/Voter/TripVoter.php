<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Enum\TripStatus;
use App\Entity\Trip;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TripVoter extends Voter
{
    public const BOOK = 'TRIP_BOOK';
    public const MANAGE = 'TRIP_MANAGE';
    public const CANCEL = 'TRIP_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::BOOK, self::MANAGE, self::CANCEL], true)
            && $subject instanceof Trip;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Trip $trip */
        $trip = $subject;
        $isDriver = $trip->getDriver()?->getId() === $user->getId();
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        return match ($attribute) {
            self::BOOK => !$isDriver && $trip->getStatus() === TripStatus::SCHEDULED,
            self::MANAGE => $isDriver || $isAdmin,
            self::CANCEL => ($isDriver || $isAdmin) && $trip->getStatus() === TripStatus::SCHEDULED,
            default => false,
        };
    }
}
