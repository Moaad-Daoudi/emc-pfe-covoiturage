<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Exception\SeatsNotAvailableException;
use App\Exception\SelfBookingNotAllowedException;
use App\Service\RideBookingManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test validating atomic booking transactions, pessimistic locking and overbooking prevention (T-202).
 */
final class BookingConcurrencyTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RideBookingManager $bookingManager;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $em;

        /** @var \App\Repository\TripSegmentRepository $segmentRepository */
        $segmentRepository = $this->entityManager->getRepository(TripSegment::class);
        $this->bookingManager = new RideBookingManager($this->entityManager, $segmentRepository);

        // Purge test tables before each test
        $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE booking_segment');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE booking');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE trip_segment');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE trip_stop');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE trip');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE vehicle');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE user');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE city');
        $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testAtomicBookingDecrementsSeatsAndPreventsOverbooking(): void
    {
        // 1. Arrange: Setup realistic test route with only 1 seat available
        $driver = (new User())
            ->setEmail('driver@covoit.test')
            ->setPassword('hash')
            ->setFirstName('Driver')
            ->setLastName('Test')
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable());

        $passenger1 = (new User())
            ->setEmail('pass1@covoit.test')
            ->setPassword('hash')
            ->setFirstName('Pass1')
            ->setLastName('Test')
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable());

        $passenger2 = (new User())
            ->setEmail('pass2@covoit.test')
            ->setPassword('hash')
            ->setFirstName('Pass2')
            ->setLastName('Test')
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable());

        $vehicle = (new Vehicle())
            ->setOwner($driver)
            ->setBrand('Dacia')
            ->setModel('Duster')
            ->setColor('White')
            ->setLicensePlate('12345-A-1')
            ->setSeats(4)
            ->setAirConditioned(true);

        $cityAgadir = (new City())->setName('Agadir')->setLatitude(30.42)->setLongitude(-9.59);
        $cityMarrakech = (new City())->setName('Marrakech')->setLatitude(31.62)->setLongitude(-7.98);

        $departureTime = new \DateTimeImmutable('+3 days');
        $trip = (new Trip())
            ->setDriver($driver)
            ->setVehicle($vehicle)
            ->setDepartureCity($cityAgadir)
            ->setArrivalCity($cityMarrakech)
            ->setDepartureAt($departureTime)
            ->setStatus(TripStatus::SCHEDULED)
            ->setTotalSeats(4)
            ->setPriceTotal(120)
            ->setLuggageAllowed(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $stop0 = (new TripStop())
            ->setTrip($trip)
            ->setCity($cityAgadir)
            ->setStopOrder(0)
            ->setEstimatedArrivalAt($departureTime);

        $stop1 = (new TripStop())
            ->setTrip($trip)
            ->setCity($cityMarrakech)
            ->setStopOrder(1)
            ->setEstimatedArrivalAt($departureTime->modify('+3 hours'));

        // Only 1 seat available!
        $segment = (new TripSegment())
            ->setTrip($trip)
            ->setFromStop($stop0)
            ->setToStop($stop1)
            ->setSegmentOrder(0)
            ->setAvailableSeats(1)
            ->setPrice(120);

        $this->entityManager->persist($driver);
        $this->entityManager->persist($passenger1);
        $this->entityManager->persist($passenger2);
        $this->entityManager->persist($vehicle);
        $this->entityManager->persist($cityAgadir);
        $this->entityManager->persist($cityMarrakech);
        $this->entityManager->persist($trip);
        $this->entityManager->persist($stop0);
        $this->entityManager->persist($stop1);
        $this->entityManager->persist($segment);
        $this->entityManager->flush();

        // 2. Act: Passenger 1 successfully books the last seat
        $booking1 = $this->bookingManager->book($passenger1, $trip, $stop0, $stop1, seats: 1);

        $this->assertNotNull($booking1->getId());
        $this->assertSame(BookingStatus::CONFIRMED, $booking1->getStatus());
        $this->assertSame(1, $booking1->getSeatsBooked());
        $this->assertSame(120, $booking1->getTotalPrice());

        // Refresh segment to verify atomic database decrementation
        $this->entityManager->refresh($segment);
        $this->assertSame(0, $segment->getAvailableSeats(), 'Available seats must be decremented to 0');

        // 3. Assert: Passenger 2 attempts to book on the same segment -> Must fail with SeatsNotAvailableException
        $this->expectException(SeatsNotAvailableException::class);
        $this->expectExceptionMessage('Places insuffisantes');

        try {
            $this->bookingManager->book($passenger2, $trip, $stop0, $stop1, seats: 1);
        } finally {
            // Verify database remained integer and consistent after rollback
            $this->entityManager->clear();
            $reloadedSegment = $this->entityManager->find(TripSegment::class, $segment->getId());
            $this->assertSame(0, $reloadedSegment->getAvailableSeats(), 'Seats must remain 0 after rejected booking');
        }
    }

    public function testDriverCannotBookOnOwnTrip(): void
    {
        $driver = (new User())
            ->setEmail('driver_self@covoit.test')
            ->setPassword('hash')
            ->setFirstName('Driver')
            ->setLastName('Self')
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable());

        $vehicle = (new Vehicle())
            ->setOwner($driver)
            ->setBrand('Renault')
            ->setModel('Clio')
            ->setColor('Black')
            ->setLicensePlate('67890-B-1')
            ->setSeats(4)
            ->setAirConditioned(true);

        $city1 = (new City())->setName('Rabat')->setLatitude(34.02)->setLongitude(-6.83);
        $city2 = (new City())->setName('Casablanca')->setLatitude(33.57)->setLongitude(-7.58);

        $trip = (new Trip())
            ->setDriver($driver)
            ->setVehicle($vehicle)
            ->setDepartureCity($city1)
            ->setArrivalCity($city2)
            ->setDepartureAt(new \DateTimeImmutable('+2 days'))
            ->setStatus(TripStatus::SCHEDULED)
            ->setTotalSeats(4)
            ->setPriceTotal(40)
            ->setLuggageAllowed(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $stop0 = (new TripStop())->setTrip($trip)->setCity($city1)->setStopOrder(0)->setEstimatedArrivalAt(new \DateTimeImmutable('+2 days'));
        $stop1 = (new TripStop())->setTrip($trip)->setCity($city2)->setStopOrder(1)->setEstimatedArrivalAt(new \DateTimeImmutable('+2 days +1 hour'));
        $segment = (new TripSegment())->setTrip($trip)->setFromStop($stop0)->setToStop($stop1)->setSegmentOrder(0)->setAvailableSeats(3)->setPrice(40);

        $this->entityManager->persist($driver);
        $this->entityManager->persist($vehicle);
        $this->entityManager->persist($city1);
        $this->entityManager->persist($city2);
        $this->entityManager->persist($trip);
        $this->entityManager->persist($stop0);
        $this->entityManager->persist($stop1);
        $this->entityManager->persist($segment);
        $this->entityManager->flush();

        $this->expectException(SelfBookingNotAllowedException::class);
        $this->bookingManager->book($driver, $trip, $stop0, $stop1, 1);
    }
}
