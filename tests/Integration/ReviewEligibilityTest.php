<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\Review;
use App\Exception\ReviewNotAllowedException;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Service\ReviewManager;
use App\Tests\Support\CarpoolingTestHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReviewEligibilityTest extends KernelTestCase
{
    use CarpoolingTestHelperTrait;

    private EntityManagerInterface $em;
    private ReviewManager $reviewManager;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        /** @var BookingRepository $bookingRepo */
        $bookingRepo = $this->em->getRepository(Booking::class);
        /** @var ReviewRepository $reviewRepo */
        $reviewRepo = $this->em->getRepository(Review::class);

        $this->reviewManager = new ReviewManager($this->em, $bookingRepo, $reviewRepo);
        $this->resetDatabase($this->em);
    }

    public function testCannotReviewScheduledOrCancelledTrip(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $passenger = $this->createUser($this->em, 'p@test.ma', 'Youssef');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            new \DateTimeImmutable('+1 day'),
            seats: 2,
            segmentPrices: [100],
            status: TripStatus::SCHEDULED
        );

        $booking = (new Booking())
            ->setPassenger($passenger)
            ->setTrip($trip)
            ->setBoardingStop($stops[0])
            ->setAlightingStop($stops[1])
            ->setSeatsBooked(1)
            ->setTotalPrice(100)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($booking);
        $this->em->flush();

        self::assertFalse($this->reviewManager->canReview($passenger, $driver, $trip));

        $this->expectException(ReviewNotAllowedException::class);
        $this->reviewManager->createReview($passenger, $driver, $trip, 5, 'Super');
    }

    public function testConfirmedParticipantsCanReviewBidirectionallyOnceAfterCompletion(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $p1 = $this->createUser($this->em, 'p1@test.ma', 'Youssef');
        $p2 = $this->createUser($this->em, 'p2@test.ma', 'Salma');
        $stranger = $this->createUser($this->em, 'stranger@test.ma', 'Inconnu');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            new \DateTimeImmutable('-2 days'),
            seats: 3,
            segmentPrices: [100],
            status: TripStatus::COMPLETED
        );

        foreach ([$p1, $p2] as $passenger) {
            $b = (new Booking())
                ->setPassenger($passenger)
                ->setTrip($trip)
                ->setBoardingStop($stops[0])
                ->setAlightingStop($stops[1])
                ->setSeatsBooked(1)
                ->setTotalPrice(100)
                ->setStatus(BookingStatus::CONFIRMED)
                ->setCreatedAt(new \DateTimeImmutable('-3 days'));
            $this->em->persist($b);
        }
        $this->em->flush();

        // Passager -> Passager interdit ; Étranger -> Conducteur interdit
        self::assertFalse($this->reviewManager->canReview($p1, $p2, $trip));
        self::assertFalse($this->reviewManager->canReview($stranger, $driver, $trip));

        // Passager -> Conducteur autorisé 1 fois
        self::assertTrue($this->reviewManager->canReview($p1, $driver, $trip));
        $review = $this->reviewManager->createReview($p1, $driver, $trip, 5, 'Conducteur très ponctuel');
        self::assertSame(5, $review->getRating());
        self::assertFalse($this->reviewManager->canReview($p1, $driver, $trip));

        // Conducteur -> Passager autorisé
        self::assertTrue($this->reviewManager->canReview($driver, $p1, $trip));
        $this->reviewManager->createReview($driver, $p1, $trip, 4, 'Passager sympathique');

        // 2e avis sur la même cible bloqué
        $this->expectException(ReviewNotAllowedException::class);
        $this->reviewManager->createReview($p1, $driver, $trip, 5, 'Doublon');
    }
}
