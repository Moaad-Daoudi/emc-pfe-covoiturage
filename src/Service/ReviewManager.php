<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\TripStatus;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\ReviewNotAllowedException;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service central pour la gestion des avis et l'éligibilité (T-204).
 *
 * Invariants:
 * 1. Uniquement après TripStatus::COMPLETED.
 * 2. Notation bidirectionnelle Conducteur <-> Passager avec réservation CONFIRMED.
 * 3. Jamais d'auto-évaluation ni d'évaluation Passager <-> Passager.
 * 4. 1 seul avis par triplet (author, target, trip).
 * 5. Note comprise entre 1 et 5.
 */
class ReviewManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly ReviewRepository $reviewRepository,
    ) {
    }

    /**
     * Vérifie si $author a le droit de noter $target pour ce $trip.
     */
    public function canReview(User $author, User $target, Trip $trip): bool
    {
        if ($trip->getStatus() !== TripStatus::COMPLETED) {
            return false;
        }

        if ($author->getId() !== null && $target->getId() !== null && $author->getId() === $target->getId()) {
            return false;
        }

        $driver = $trip->getDriver();
        if ($driver === null) {
            return false;
        }

        $authorIsDriver = ($driver->getId() === $author->getId());
        $targetIsDriver = ($driver->getId() === $target->getId());

        // Cas 1 : Passager -> Conducteur
        if (!$authorIsDriver && $targetIsDriver) {
            $participated = $this->bookingRepository->hasConfirmedBooking($author, $trip);
        }
        // Cas 2 : Conducteur -> Passager confirmé
        elseif ($authorIsDriver && !$targetIsDriver) {
            $participated = $this->bookingRepository->hasConfirmedBooking($target, $trip);
        }
        // Cas interdit : Passager <-> Passager ou tiers
        else {
            return false;
        }

        if (!$participated) {
            return false;
        }

        return !$this->reviewRepository->hasAlreadyReviewed($author, $target, $trip);
    }

    /**
     * Retourne la liste des cibles encore non notées par $author sur un trajet donné.
     *
     * @return User[]
     */
    public function getReviewableTargets(User $author, Trip $trip): array
    {
        if ($trip->getStatus() !== TripStatus::COMPLETED) {
            return [];
        }

        $driver = $trip->getDriver();
        if ($driver === null) {
            return [];
        }

        $targets = [];

        // Si l'auteur est un passager, il peut noter le conducteur s'il ne l'a pas déjà fait
        if ($driver->getId() !== $author->getId()) {
            if ($this->canReview($author, $driver, $trip)) {
                $targets[] = $driver;
            }

            return $targets;
        }

        // Si l'auteur est le conducteur, il peut noter chaque passager confirmé non encore évalué
        $seenPassengerIds = [];
        foreach ($this->bookingRepository->findConfirmedByTrip($trip) as $booking) {
            $passenger = $booking->getPassenger();
            if ($passenger === null || isset($seenPassengerIds[$passenger->getId()])) {
                continue;
            }
            $seenPassengerIds[$passenger->getId()] = true;

            if ($this->canReview($author, $passenger, $trip)) {
                $targets[] = $passenger;
            }
        }

        return array_values($targets);
    }

    /**
     * Crée et persiste un avis vérifié.
     *
     * @throws ReviewNotAllowedException
     */
    public function createReview(
        User $author,
        User $target,
        Trip $trip,
        int $rating,
        ?string $comment = null,
        ?\DateTimeImmutable $createdAt = null
    ): Review {
        if ($rating < 1 || $rating > 5) {
            throw new ReviewNotAllowedException('La note doit être comprise entre 1 et 5 étoiles.');
        }

        if ($trip->getStatus() !== TripStatus::COMPLETED) {
            throw new ReviewNotAllowedException('Un avis ne peut être déposé qu\'après un trajet terminé.');
        }

        if (!$this->canReview($author, $target, $trip)) {
            throw new ReviewNotAllowedException('Vous n\'êtes pas autorisé à évaluer ce participant pour ce trajet.');
        }

        $cleanComment = $comment !== null ? trim($comment) : null;
        if ($cleanComment === '') {
            $cleanComment = null;
        }

        $review = (new Review())
            ->setAuthor($author)
            ->setTarget($target)
            ->setTrip($trip)
            ->setRating($rating)
            ->setComment($cleanComment)
            ->setCreatedAt($createdAt ?? new \DateTimeImmutable());

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $review;
    }
}
