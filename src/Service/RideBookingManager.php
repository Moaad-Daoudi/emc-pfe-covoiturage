<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\BookingSegment;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Exception\InvalidTripSegmentException;
use App\Exception\SeatsNotAvailableException;
use App\Exception\SelfBookingNotAllowedException;
use App\Exception\TripNotBookableException;
use App\Repository\TripSegmentRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RideBookingManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TripSegmentRepository $segmentRepository,
    ) {
    }

    /**
     * Valide la cohérence des arrêts et retourne les segments consécutifs traversés.
     *
     * @return TripSegment[]
     * @throws InvalidTripSegmentException
     */
    public function resolveSegments(
        Trip $trip,
        TripStop $boardingStop,
        TripStop $alightingStop,
        bool $lockForUpdate = false,
    ): array {
        if ($boardingStop->getTrip()?->getId() !== $trip->getId()
            || $alightingStop->getTrip()?->getId() !== $trip->getId()
        ) {
            throw new InvalidTripSegmentException('Les arrêts sélectionnés n\'appartiennent pas à ce trajet.');
        }

        $startOrder = $boardingStop->getStopOrder();
        $endOrder = $alightingStop->getStopOrder();

        if ($startOrder >= $endOrder) {
            throw new InvalidTripSegmentException(sprintf(
                'L\'arrêt de descente (#%d) doit être postérieur à l\'arrêt de montée (#%d).',
                $endOrder,
                $startOrder
            ));
        }

        $segments = $this->segmentRepository->findBetweenStopOrders(
            $trip,
            $startOrder,
            $endOrder,
            $lockForUpdate
        );

        $expectedCount = $endOrder - $startOrder;
        if (count($segments) !== $expectedCount) {
            throw new InvalidTripSegmentException('Tronçon incomplet ou corrompu pour ce trajet.');
        }

        return $segments;
    }

    /**
     * Retourne le nombre maximum de places réservables sur une portion [i, j).
     * Capacité = min(availableSeats sur tous les tronçons intermédiaires).
     */
    public function getAvailableSeats(Trip $trip, TripStop $boardingStop, TripStop $alightingStop): int
    {
        if ($trip->getStatus() !== TripStatus::SCHEDULED) {
            return 0;
        }

        if ($boardingStop->getEstimatedArrivalAt() <= new \DateTimeImmutable()) {
            return 0;
        }

        $segments = $this->resolveSegments($trip, $boardingStop, $alightingStop);

        if (empty($segments)) {
            return 0;
        }

        return min(array_map(
            static fn (TripSegment $segment): int => $segment->getAvailableSeats(),
            $segments
        ));
    }

    /**
     * Alias ergonomique pour getAvailableSeats.
     */
    public function getAvailableSeatsForPath(Trip $trip, TripStop $boardingStop, TripStop $alightingStop): int
    {
        return $this->getAvailableSeats($trip, $boardingStop, $alightingStop);
    }

    /**
     * Calcule le prix total pour un sous-tronçon et un nombre de places donné.
     */
    public function calculateSubTripPrice(
        Trip $trip,
        TripStop $boardingStop,
        TripStop $alightingStop,
        int $seats = 1,
    ): int {
        if ($seats < 1) {
            throw new InvalidTripSegmentException('Le nombre de places doit être au moins égal à 1.');
        }

        $segments = $this->resolveSegments($trip, $boardingStop, $alightingStop);
        $unitPrice = array_sum(array_map(
            static fn (TripSegment $segment): int => $segment->getPrice(),
            $segments
        ));

        return (int) ($unitPrice * $seats);
    }

    /**
     * Alias ergonomique pour calculateSubTripPrice.
     */
    public function calculatePriceForPath(Trip $trip, TripStop $boardingStop, TripStop $alightingStop, int $seatsCount = 1): int
    {
        return $this->calculateSubTripPrice($trip, $boardingStop, $alightingStop, $seatsCount);
    }

    /**
     * Crée une réservation confirmée et décrémente les segments concernés.
     * En T-202, ce flux sera encadré avec wrapInTransaction + lockForUpdate: true.
     *
     * @throws SelfBookingNotAllowedException Si le passager est le conducteur
     * @throws TripNotBookableException Si le trajet n'est plus ouvert ou est déjà parti
     * @throws SeatsNotAvailableException Si la capacité est insuffisante sur un tronçon
     * @throws InvalidTripSegmentException En cas d'incohérence sur les arrêts
     */
    public function book(
        User $passenger,
        Trip $trip,
        TripStop $boardingStop,
        TripStop $alightingStop,
        int $seats = 1,
    ): Booking {
        if ($seats < 1) {
            throw new InvalidTripSegmentException('Vous devez réserver au moins 1 place.');
        }

        if ($trip->getDriver()?->getId() !== null && $trip->getDriver()?->getId() === $passenger->getId()) {
            throw new SelfBookingNotAllowedException();
        }

        if ($trip->getStatus() !== TripStatus::SCHEDULED) {
            throw new TripNotBookableException('Ce trajet n\'est plus ouvert à la réservation.');
        }

        if ($boardingStop->getEstimatedArrivalAt() <= new \DateTimeImmutable()) {
            throw new TripNotBookableException('L\'horaire de montée pour ce tronçon est déjà dépassé.');
        }

        $segments = $this->resolveSegments($trip, $boardingStop, $alightingStop);

        // Vérification stricte de capacité sur CHAQUE segment intermédiaire
        foreach ($segments as $segment) {
            if ($segment->getAvailableSeats() < $seats) {
                $segmentDetails = sprintf(
                    '%s → %s',
                    $segment->getFromStop()?->getCity()?->getName() ?? 'Départ',
                    $segment->getToStop()?->getCity()?->getName() ?? 'Arrivée'
                );
                throw new SeatsNotAvailableException(
                    requested: $seats,
                    available: $segment->getAvailableSeats(),
                    segmentDetails: $segmentDetails
                );
            }
        }

        $unitPrice = 0;
        foreach ($segments as $segment) {
            $segment->setAvailableSeats($segment->getAvailableSeats() - $seats);
            $unitPrice += $segment->getPrice();
        }

        $booking = (new Booking())
            ->setPassenger($passenger)
            ->setTrip($trip)
            ->setBoardingStop($boardingStop)
            ->setAlightingStop($alightingStop)
            ->setSeatsBooked($seats)
            ->setTotalPrice($unitPrice * $seats)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($booking);

        foreach ($segments as $segment) {
            $bookingSegment = (new BookingSegment())
                ->setBooking($booking)
                ->setSegment($segment)
                ->setSeatsCount($seats);

            $this->entityManager->persist($bookingSegment);
        }

        $this->entityManager->flush();

        return $booking;
    }

    /**
     * Alias ergonomique pour book.
     */
    public function bookTrip(
        User $passenger,
        Trip $trip,
        TripStop $boardingStop,
        TripStop $alightingStop,
        int $seatsCount = 1
    ): Booking {
        return $this->book($passenger, $trip, $boardingStop, $alightingStop, $seatsCount);
    }
}
