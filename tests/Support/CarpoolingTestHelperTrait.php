<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Enum\TripStatus;
use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;

trait CarpoolingTestHelperTrait
{
    private function resetDatabase(EntityManagerInterface $em): void
    {
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['review', 'booking_segment', 'booking', 'trip_segment', 'trip_stop', 'trip', 'vehicle', 'city', 'user'] as $table) {
            $conn->executeStatement(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $em->clear();
    }

    private function createUser(EntityManagerInterface $em, string $email, string $firstName): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPassword('$2y$13$test_hash')
            ->setFirstName($firstName)
            ->setLastName('Test')
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);

        return $user;
    }

    private function createCity(EntityManagerInterface $em, string $name, float $lat = 30.4, float $lng = -9.5): City
    {
        $city = (new City())
            ->setName($name)
            ->setLatitude($lat)
            ->setLongitude($lng);

        $em->persist($city);

        return $city;
    }

    private function createVehicle(EntityManagerInterface $em, User $owner): Vehicle
    {
        $vehicle = (new Vehicle())
            ->setOwner($owner)
            ->setBrand('Dacia')
            ->setModel('Duster')
            ->setColor('Gris')
            ->setLicensePlate('12345-A-33')
            ->setSeats(3)
            ->setAirConditioned(true);

        $em->persist($vehicle);

        return $vehicle;
    }

    /**
     * Crée un trajet multi-étapes (ex: Agadir -> Essaouira -> Marrakech) avec ses TripStop et TripSegment.
     *
     * @param City[] $cities
     * @param int[]  $segmentPrices
     *
     * @return array{trip: Trip, stops: TripStop[], segments: TripSegment[]}
     */
    private function createMultiStopTrip(
        EntityManagerInterface $em,
        User $driver,
        Vehicle $vehicle,
        array $cities,
        \DateTimeImmutable $departureAt,
        int $seats = 2,
        array $segmentPrices = [60, 50],
        TripStatus $status = TripStatus::SCHEDULED,
    ): array {
        $trip = (new Trip())
            ->setDriver($driver)
            ->setVehicle($vehicle)
            ->setDepartureCity($cities[0])
            ->setArrivalCity($cities[array_key_last($cities)])
            ->setDepartureAt($departureAt)
            ->setTotalSeats($seats)
            ->setPriceTotal(array_sum($segmentPrices))
            ->setLuggageAllowed(true)
            ->setStatus($status)
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($trip);

        $stops = [];
        foreach ($cities as $index => $city) {
            $stop = (new TripStop())
                ->setTrip($trip)
                ->setCity($city)
                ->setStopOrder($index)
                ->setEstimatedArrivalAt($departureAt->modify(sprintf('+%d hours', $index * 2)));

            $em->persist($stop);
            $stops[] = $stop;
        }

        $segments = [];
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $segment = (new TripSegment())
                ->setTrip($trip)
                ->setFromStop($stops[$i])
                ->setToStop($stops[$i + 1])
                ->setSegmentOrder($i)
                ->setAvailableSeats($seats)
                ->setPrice($segmentPrices[$i] ?? 50);

            $em->persist($segment);
            $segments[] = $segment;
        }

        $em->flush();

        return ['trip' => $trip, 'stops' => $stops, 'segments' => $segments];
    }
}
