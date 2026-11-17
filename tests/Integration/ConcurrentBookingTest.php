<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Enum\BookingStatus;
use App\Entity\Booking;
use App\Tests\Support\CarpoolingTestHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class ConcurrentBookingTest extends KernelTestCase
{
    use CarpoolingTestHelperTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;
        $this->resetDatabase($this->em);
    }

    public function testTwoSimultaneousBookingsForLastSeatAllowOnlyOneWinner(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $p1 = $this->createUser($this->em, 'p1@test.ma', 'Amina');
        $p2 = $this->createUser($this->em, 'p2@test.ma', 'Youssef');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        // 1 seule place disponible en base (commitée)
        ['trip' => $trip, 'stops' => $stops, 'segments' => $segments] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            new \DateTimeImmutable('+3 days'),
            seats: 1,
            segmentPrices: [120]
        );

        $worker = dirname(__DIR__) . '/Support/attempt_booking.php';

        $proc1 = new Process([
            'php', $worker,
            (string) $p1->getId(),
            (string) $trip->getId(),
            (string) $stops[0]->getId(),
            (string) $stops[1]->getId(),
        ]);
        $proc2 = new Process([
            'php', $worker,
            (string) $p2->getId(),
            (string) $trip->getId(),
            (string) $stops[0]->getId(),
            (string) $stops[1]->getId(),
        ]);

        // Lancement simultané des 2 processus
        $proc1->start();
        $proc2->start();
        $proc1->wait();
        $proc2->wait();

        $results = [trim($proc1->getOutput()), trim($proc2->getOutput())];
        sort($results);

        // Exactement 1 BOOKED et 1 REJECTED (jamais 2 BOOKED)
        self::assertSame(['BOOKED', 'REJECTED'], $results);

        $this->em->clear();
        $confirmedCount = $this->em->getRepository(Booking::class)->count([
            'trip' => $trip->getId(),
            'status' => BookingStatus::CONFIRMED,
        ]);
        self::assertSame(1, $confirmedCount);

        $freshSegment = $this->em->find(get_class($segments[0]), $segments[0]->getId());
        self::assertSame(0, $freshSegment->getAvailableSeats());
    }
}
