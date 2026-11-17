<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class BookingVoter extends Voter
{
    public const CANCEL_PASSENGER = 'BOOKING_CANCEL_PASSENGER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CANCEL_PASSENGER && $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Booking $booking */
        $booking = $subject;
        $trip = $booking->getTrip();

        $isPassenger = $booking->getPassenger()?->getId() === $user->getId();
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        return ($isPassenger || $isAdmin)
            && $booking->getStatus() === BookingStatus::CONFIRMED
            && $trip !== null
            && $trip->getStatus() === TripStatus::SCHEDULED;
    }
}
