<?php

namespace App\DataFixtures;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\BookingSegment;
use App\Entity\City;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TripFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            CityFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // -------------------------------------------------------------------------
        // Helper closures to fetch cities and users
        // -------------------------------------------------------------------------
        $city = fn(string $ref): City => $this->getReference($ref, City::class);
        $user = fn(string $ref): User => $this->getReference($ref, User::class);
        $vehicle = fn(string $ref): Vehicle => $this->getReference($ref, Vehicle::class);

        // =========================================================================
        // TRIP 1: Amina (Agadir -> Chichaoua -> Marrakech) [SCHEDULED]
        // =========================================================================
        $trip1 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_AMINA),
            vehicle: $vehicle('vehicle_amina'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Gare Routière Al Massira', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_CHICHAOUA), 'meeting' => 'Station Afriquia Centre', 'offsetHours' => 2.5],
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Gare ONCF Marrakech', 'offsetHours' => 3.5],
            ],
            segmentPrices: [80, 50],
            departureAt: new \DateTimeImmutable('+3 days 08:30:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 245.0
        );

        // =========================================================================
        // TRIP 2: Amina (Agadir -> Essaouira) [COMPLETED]
        // Passenger: Karim (CONFIRMED 1 seat) + Review left
        // =========================================================================
        $trip2 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_AMINA),
            vehicle: $vehicle('vehicle_amina'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Pharmacie Al Inbiâat, Av Hassan II', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_ESSAOUIRA), 'meeting' => 'Bab Doukkala, Essaouira', 'offsetHours' => 2.8],
            ],
            segmentPrices: [90],
            departureAt: new \DateTimeImmutable('-5 days 14:00:00'),
            totalSeats: 3,
            status: TripStatus::COMPLETED,
            luggageAllowed: true,
            distanceKm: 175.0
        );

        // Karim booked 1 seat on Trip 2 (Agadir -> Essaouira)
        $this->createBooking(
            manager: $manager,
            trip: $trip2,
            passenger: $user(UserFixtures::USER_KARIM),
            fromStopIndex: 0,
            toStopIndex: 1,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-6 days 10:00:00')
        );

        // Review from Karim to Amina
        $this->createReview(
            manager: $manager,
            trip: $trip2,
            author: $user(UserFixtures::USER_KARIM),
            target: $user(UserFixtures::USER_AMINA),
            rating: 5,
            comment: 'Conduite très sécurisante sur la route côtière, véhicule très propre et climatisé. Merci Amina !',
            createdAt: new \DateTimeImmutable('-5 days 18:00:00')
        );

        // =========================================================================
        // TRIP 3: Karim (Casablanca -> Rabat -> Tanger) [SCHEDULED]
        // =========================================================================
        $trip3 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_KARIM),
            vehicle: $vehicle('vehicle_karim'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Technopark Casablanca', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_RABAT), 'meeting' => 'Gare Rabat Agdal', 'offsetHours' => 1.2],
                ['city' => $city(CityFixtures::CITY_TANGER), 'meeting' => 'Tanger City Center ONCF', 'offsetHours' => 3.8],
            ],
            segmentPrices: [40, 140],
            departureAt: new \DateTimeImmutable('+2 days 07:00:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 340.0
        );

        // Mehdi books 1 seat on Casa -> Rabat
        $this->createBooking(
            manager: $manager,
            trip: $trip3,
            passenger: $user(UserFixtures::USER_MEHDI),
            fromStopIndex: 0,
            toStopIndex: 1,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-1 day 12:00:00')
        );

        // =========================================================================
        // TRIP 4: Karim (Marrakech -> Chichaoua -> Agadir) [SCHEDULED]
        // Passenger: Youssef books Chichaoua -> Agadir (sub-segment test)
        // =========================================================================
        $trip4 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_KARIM),
            vehicle: $vehicle('vehicle_karim'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Gare Routière Bab Doukkala', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_CHICHAOUA), 'meeting' => 'Rond-point entrée autoroute', 'offsetHours' => 1.0],
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Supermarché Marjane Founty', 'offsetHours' => 3.2],
            ],
            segmentPrices: [40, 90],
            departureAt: new \DateTimeImmutable('+5 days 16:30:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 250.0
        );

        // Youssef (demo passenger) books sub-segment: Chichaoua -> Agadir (stops index 1 -> 2)
        $this->createBooking(
            manager: $manager,
            trip: $trip4,
            passenger: $user(UserFixtures::USER_YOUSSEF),
            fromStopIndex: 1,
            toStopIndex: 2,
            seatsBooked: 2,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-12 hours')
        );

        // =========================================================================
        // TRIP 5: Karim (Casablanca -> Meknès -> Fès) [COMPLETED]
        // Passenger: Youssef on sub-segment Meknès -> Fès with reciprocal reviews!
        // =========================================================================
        $trip5 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_KARIM),
            vehicle: $vehicle('vehicle_karim'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Station Tramway Oasis', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_MEKNES), 'meeting' => 'Gare Meknès Ville', 'offsetHours' => 2.5],
                ['city' => $city(CityFixtures::CITY_FES), 'meeting' => 'Gare Fès Ville', 'offsetHours' => 3.3],
            ],
            segmentPrices: [80, 35],
            departureAt: new \DateTimeImmutable('-10 days 09:00:00'),
            totalSeats: 3,
            status: TripStatus::COMPLETED,
            luggageAllowed: true,
            distanceKm: 295.0
        );

        // Youssef booked Meknès -> Fès (index 1 -> 2)
        $this->createBooking(
            manager: $manager,
            trip: $trip5,
            passenger: $user(UserFixtures::USER_YOUSSEF),
            fromStopIndex: 1,
            toStopIndex: 2,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-12 days 15:00:00')
        );

        // Review from passenger Youssef to driver Karim
        $this->createReview(
            manager: $manager,
            trip: $trip5,
            author: $user(UserFixtures::USER_YOUSSEF),
            target: $user(UserFixtures::USER_KARIM),
            rating: 5,
            comment: 'Karim est un super conducteur, ponctuel et très sympathique. Voyage rapide et sans encombre !',
            createdAt: new \DateTimeImmutable('-10 days 14:00:00')
        );

        // Review from driver Karim to passenger Youssef
        $this->createReview(
            manager: $manager,
            trip: $trip5,
            author: $user(UserFixtures::USER_KARIM),
            target: $user(UserFixtures::USER_YOUSSEF),
            rating: 5,
            comment: 'Youssef est ponctuel, agréable et respectueux. Je recommande vivement ce passager !',
            createdAt: new \DateTimeImmutable('-10 days 15:30:00')
        );

        // =========================================================================
        // TRIP 6: Karim (Agadir -> Essaouira -> Safi -> Casablanca) [COMPLETED]
        // 4 stops, 3 segments. Multi-passenger bookings.
        // =========================================================================
        $trip6 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_KARIM),
            vehicle: $vehicle('vehicle_karim'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Hôtel Tivoli Agadir', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_ESSAOUIRA), 'meeting' => 'Rond-point Bab Marrakech', 'offsetHours' => 2.8],
                ['city' => $city(CityFixtures::CITY_SAFI), 'meeting' => 'Place Sidi Bouzid', 'offsetHours' => 4.5],
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Gare Casa-Voyageurs', 'offsetHours' => 7.2],
            ],
            segmentPrices: [80, 50, 90],
            departureAt: new \DateTimeImmutable('-18 days 08:00:00'),
            totalSeats: 3,
            status: TripStatus::COMPLETED,
            luggageAllowed: true,
            distanceKm: 510.0
        );

        // Leila booked Agadir -> Safi (stops 0 -> 2, spanning 2 segments)
        $this->createBooking(
            manager: $manager,
            trip: $trip6,
            passenger: $user(UserFixtures::USER_LEILA),
            fromStopIndex: 0,
            toStopIndex: 2,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-20 days 11:00:00')
        );

        // Mehdi booked Safi -> Casablanca (stops 2 -> 3)
        $this->createBooking(
            manager: $manager,
            trip: $trip6,
            passenger: $user(UserFixtures::USER_MEHDI),
            fromStopIndex: 2,
            toStopIndex: 3,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-19 days 18:00:00')
        );

        $this->createReview(
            manager: $manager,
            trip: $trip6,
            author: $user(UserFixtures::USER_LEILA),
            target: $user(UserFixtures::USER_KARIM),
            rating: 5,
            comment: 'Trajet parfait jusqu’à Safi. Ponctuel et voiture spacieuse.',
            createdAt: new \DateTimeImmutable('-18 days 16:00:00')
        );

        // =========================================================================
        // TRIP 7: Omar (Casablanca -> Rabat) [SCHEDULED]
        // Passenger: Amina books 1 seat as passenger! (Requirement: Amina dual role)
        // =========================================================================
        $trip7 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_OMAR),
            vehicle: $vehicle('vehicle_omar'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Marina de Casablanca', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_RABAT), 'meeting' => 'Gare Rabat Ville', 'offsetHours' => 1.1],
            ],
            segmentPrices: [40],
            departureAt: new \DateTimeImmutable('+1 day 09:00:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 87.0
        );

        // Amina books 1 seat as passenger
        $this->createBooking(
            manager: $manager,
            trip: $trip7,
            passenger: $user(UserFixtures::USER_AMINA),
            fromStopIndex: 0,
            toStopIndex: 1,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-8 hours')
        );

        // =========================================================================
        // TRIP 8: Fatima (Agadir -> Taroudant) [SCHEDULED]
        // Regional Souss-Massa commuter
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_FATIMA),
            vehicle: $vehicle('vehicle_fatima'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Faculté des Sciences Ibn Zohr', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_TAROUDANT), 'meeting' => 'Bab Zorgan', 'offsetHours' => 1.2],
            ],
            segmentPrices: [30],
            departureAt: new \DateTimeImmutable('+1 day 07:30:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: false,
            distanceKm: 80.0
        );

        // =========================================================================
        // TRIP 9: Fatima (Agadir -> Tiznit) [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_FATIMA),
            vehicle: $vehicle('vehicle_fatima'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Inzegane Gare Routière', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_TIZNIT), 'meeting' => 'Place Al Mechouar', 'offsetHours' => 1.3],
            ],
            segmentPrices: [35],
            departureAt: new \DateTimeImmutable('+2 days 17:00:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 90.0
        );

        // =========================================================================
        // TRIP 10: Fatima (Agadir -> Marrakech) [COMPLETED]
        // Passenger: Khalid
        // =========================================================================
        $trip10 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_FATIMA),
            vehicle: $vehicle('vehicle_fatima'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Dcheira Station Total', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Aéroport Marrakech Menara', 'offsetHours' => 3.2],
            ],
            segmentPrices: [120],
            departureAt: new \DateTimeImmutable('-8 days 11:00:00'),
            totalSeats: 3,
            status: TripStatus::COMPLETED,
            luggageAllowed: true,
            distanceKm: 250.0
        );

        $this->createBooking(
            manager: $manager,
            trip: $trip10,
            passenger: $user(UserFixtures::USER_KHALID),
            fromStopIndex: 0,
            toStopIndex: 1,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-9 days 14:00:00')
        );

        $this->createReview(
            manager: $manager,
            trip: $trip10,
            author: $user(UserFixtures::USER_KHALID),
            target: $user(UserFixtures::USER_FATIMA),
            rating: 4,
            comment: 'Bonne conduite, arrivée pile à l’heure pour mon vol.',
            createdAt: new \DateTimeImmutable('-8 days 16:00:00')
        );

        // =========================================================================
        // TRIP 11: Omar (Casablanca -> Marrakech) [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_OMAR),
            vehicle: $vehicle('vehicle_omar'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Sidi Maârouf Zenith', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Guéliz Plaza', 'offsetHours' => 2.8],
            ],
            segmentPrices: [110],
            departureAt: new \DateTimeImmutable('+4 days 15:00:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 240.0
        );

        // =========================================================================
        // TRIP 12: Omar (Tanger -> Rabat -> Casablanca) [COMPLETED]
        // =========================================================================
        $trip12 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_OMAR),
            vehicle: $vehicle('vehicle_omar'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_TANGER), 'meeting' => 'Port de Tanger Ville', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_RABAT), 'meeting' => 'Hay Riad Mahaj Riad', 'offsetHours' => 2.5],
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Casa Port', 'offsetHours' => 3.6],
            ],
            segmentPrices: [120, 45],
            departureAt: new \DateTimeImmutable('-14 days 13:00:00'),
            totalSeats: 3,
            status: TripStatus::COMPLETED,
            luggageAllowed: true,
            distanceKm: 340.0
        );

        $this->createBooking(
            manager: $manager,
            trip: $trip12,
            passenger: $user(UserFixtures::USER_LEILA),
            fromStopIndex: 0,
            toStopIndex: 2,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-16 days 10:00:00')
        );

        $this->createReview(
            manager: $manager,
            trip: $trip12,
            author: $user(UserFixtures::USER_LEILA),
            target: $user(UserFixtures::USER_OMAR),
            rating: 5,
            comment: 'Trajet autoroute direct, conduite très fluide et musique agréable.',
            createdAt: new \DateTimeImmutable('-14 days 18:00:00')
        );

        // =========================================================================
        // TRIP 13: Salma (Fès -> Meknès -> Rabat -> Casablanca) [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_SALMA),
            vehicle: $vehicle('vehicle_salma'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_FES), 'meeting' => 'Place Florence Fès', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_MEKNES), 'meeting' => 'Place Lahdim Meknès', 'offsetHours' => 0.9],
                ['city' => $city(CityFixtures::CITY_RABAT), 'meeting' => 'Gare Rabat Agdal', 'offsetHours' => 2.6],
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Boulevard d’Anfa', 'offsetHours' => 3.8],
            ],
            segmentPrices: [30, 70, 40],
            departureAt: new \DateTimeImmutable('+3 days 06:30:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 300.0
        );

        // =========================================================================
        // TRIP 14: Salma (Fès -> Meknès) [SCHEDULED] Daily shuttle
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_SALMA),
            vehicle: $vehicle('vehicle_salma'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_FES), 'meeting' => 'Borj Fez', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_MEKNES), 'meeting' => 'Marjane Meknès Route de Rabat', 'offsetHours' => 0.8],
            ],
            segmentPrices: [25],
            departureAt: new \DateTimeImmutable('+1 day 08:00:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: false,
            distanceKm: 60.0
        );

        // =========================================================================
        // TRIP 15: Salma (Casablanca -> Beni Mellal) [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_SALMA),
            vehicle: $vehicle('vehicle_salma'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Gare routière Oulad Ziane', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_BENI_MELLAL), 'meeting' => 'Aïn Asserdoun Beni Mellal', 'offsetHours' => 3.0],
            ],
            segmentPrices: [85],
            departureAt: new \DateTimeImmutable('+6 days 14:00:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 215.0
        );

        // =========================================================================
        // TRIP 16: Karim (Marrakech -> Ouarzazate) [SCHEDULED]
        // Scenic High Atlas route via Tizi n'Tichka
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_KARIM),
            vehicle: $vehicle('vehicle_karim'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Bab Agnaou, Médina', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_OUARZAZATE), 'meeting' => 'Place Al Mouahidine', 'offsetHours' => 4.2],
            ],
            segmentPrices: [95],
            departureAt: new \DateTimeImmutable('+7 days 07:00:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 195.0
        );

        // =========================================================================
        // TRIP 17: Fatima (Essaouira -> Chichaoua -> Marrakech) [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_FATIMA),
            vehicle: $vehicle('vehicle_fatima'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_ESSAOUIRA), 'meeting' => 'Gare CTM Essaouira', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_CHICHAOUA), 'meeting' => 'Café Al Mansour', 'offsetHours' => 1.5],
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Menara Mall', 'offsetHours' => 2.8],
            ],
            segmentPrices: [45, 45],
            departureAt: new \DateTimeImmutable('+4 days 11:00:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 180.0
        );

        // =========================================================================
        // TRIP 18: Omar (Rabat -> Casablanca) [SCHEDULED] Evening commuter
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_OMAR),
            vehicle: $vehicle('vehicle_omar'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_RABAT), 'meeting' => 'Ministère de l’Éducation, Bab Rouah', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Station Shell Aïn Sebaâ', 'offsetHours' => 1.1],
            ],
            segmentPrices: [40],
            departureAt: new \DateTimeImmutable('+2 days 18:30:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: false,
            distanceKm: 87.0
        );

        // =========================================================================
        // TRIP 19: Salma (Beni Mellal -> Marrakech) [COMPLETED]
        // =========================================================================
        $trip19 = $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_SALMA),
            vehicle: $vehicle('vehicle_salma'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_BENI_MELLAL), 'meeting' => 'Gare Routière Beni Mellal', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Place du 16 Novembre', 'offsetHours' => 3.1],
            ],
            segmentPrices: [90],
            departureAt: new \DateTimeImmutable('-12 days 08:30:00'),
            totalSeats: 4,
            status: TripStatus::COMPLETED,
            luggageAllowed: true,
            distanceKm: 195.0
        );

        $this->createBooking(
            manager: $manager,
            trip: $trip19,
            passenger: $user(UserFixtures::USER_YOUSSEF),
            fromStopIndex: 0,
            toStopIndex: 1,
            seatsBooked: 1,
            status: BookingStatus::CONFIRMED,
            createdAt: new \DateTimeImmutable('-13 days 20:00:00')
        );

        $this->createReview(
            manager: $manager,
            trip: $trip19,
            author: $user(UserFixtures::USER_YOUSSEF),
            target: $user(UserFixtures::USER_SALMA),
            rating: 5,
            comment: 'Très belle expérience avec Salma. Véhicule spacieux et climatisé, conduite exemplaire.',
            createdAt: new \DateTimeImmutable('-12 days 13:00:00')
        );

        // =========================================================================
        // TRIP 20: Amina (Marrakech -> Agadir) [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_AMINA),
            vehicle: $vehicle('vehicle_amina'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_MARRAKECH), 'meeting' => 'Gare ONCF Marrakech', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_AGADIR), 'meeting' => 'Gare Routière Al Massira', 'offsetHours' => 3.2],
            ],
            segmentPrices: [130],
            departureAt: new \DateTimeImmutable('+6 days 15:00:00'),
            totalSeats: 3,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 245.0
        );

        // =========================================================================
        // TRIP 21: Casablanca -> El Jadida -> Safi [SCHEDULED]
        // =========================================================================
        $this->createTrip(
            manager: $manager,
            driver: $user(UserFixtures::USER_OMAR),
            vehicle: $vehicle('vehicle_omar'),
            stopsData: [
                ['city' => $city(CityFixtures::CITY_CASABLANCA), 'meeting' => 'Morocco Mall Parking', 'offsetHours' => 0],
                ['city' => $city(CityFixtures::CITY_EL_JADIDA), 'meeting' => 'Cité Portugaise', 'offsetHours' => 1.4],
                ['city' => $city(CityFixtures::CITY_SAFI), 'meeting' => 'Château de Mer Safi', 'offsetHours' => 3.2],
            ],
            segmentPrices: [45, 60],
            departureAt: new \DateTimeImmutable('+5 days 10:00:00'),
            totalSeats: 4,
            status: TripStatus::SCHEDULED,
            luggageAllowed: true,
            distanceKm: 245.0
        );

        $manager->flush();
    }

    /**
     * Creates a trip with its sequential stops and consecutive atomic segments.
     *
     * @param array<int, array{city: City, meeting: string, offsetHours: float}> $stopsData
     * @param array<int, int> $segmentPrices
     */
    private function createTrip(
        ObjectManager $manager,
        User $driver,
        Vehicle $vehicle,
        array $stopsData,
        array $segmentPrices,
        \DateTimeImmutable $departureAt,
        int $totalSeats,
        TripStatus $status,
        bool $luggageAllowed = true,
        float $distanceKm = 100.0
    ): Trip {
        $trip = new Trip();
        $trip->setDriver($driver);
        $trip->setVehicle($vehicle);
        $trip->setDepartureCity($stopsData[0]['city']);
        $trip->setArrivalCity(end($stopsData)['city']);
        $trip->setDepartureAt($departureAt);
        $trip->setTotalSeats($totalSeats);
        $trip->setPriceTotal(array_sum($segmentPrices));
        $trip->setStatus($status);
        $trip->setLuggageAllowed($luggageAllowed);
        $trip->setDistanceKm($distanceKm);
        $trip->setCreatedAt(new \DateTimeImmutable('-20 days'));

        $manager->persist($trip);

        // 1. Create Stops
        /** @var array<int, TripStop> $stops */
        $stops = [];
        foreach ($stopsData as $index => $data) {
            $stop = new TripStop();
            $stop->setTrip($trip);
            $stop->setCity($data['city']);
            $stop->setStopOrder($index);
            $minutesOffset = (int) round($data['offsetHours'] * 60);
            $stop->setEstimatedArrivalAt($departureAt->modify("+{$minutesOffset} minutes"));
            $stop->setMeetingPoint($data['meeting']);

            $manager->persist($stop);
            $trip->getStops()->add($stop);
            $stops[] = $stop;
        }

        // 2. Create Consecutive Atomic Segments (stop[i] -> stop[i+1])
        $segmentCount = count($stops) - 1;
        for ($i = 0; $i < $segmentCount; $i++) {
            $segment = new TripSegment();
            $segment->setTrip($trip);
            $segment->setFromStop($stops[$i]);
            $segment->setToStop($stops[$i + 1]);
            $segment->setSegmentOrder($i);
            $segment->setAvailableSeats($totalSeats);
            $segment->setPrice($segmentPrices[$i] ?? 50);

            $manager->persist($segment);
            $trip->getSegments()->add($segment);
        }

        return $trip;
    }

    /**
     * Creates a confirmed booking and enforces the domain invariant by decrementing
     * available seats across all traversed atomic segments.
     */
    private function createBooking(
        ObjectManager $manager,
        Trip $trip,
        User $passenger,
        int $fromStopIndex,
        int $toStopIndex,
        int $seatsBooked,
        BookingStatus $status,
        \DateTimeImmutable $createdAt
    ): Booking {
        $stops = $trip->getStops()->toArray();
        $boardingStop = $stops[$fromStopIndex] ?? null;
        $alightingStop = $stops[$toStopIndex] ?? null;

        if (!$boardingStop || !$alightingStop) {
            throw new \InvalidArgumentException("Stops not found for indices {$fromStopIndex} -> {$toStopIndex}");
        }

        // Find segments between fromStopIndex and toStopIndex
        $segments = $trip->getSegments()->toArray();
        $relevantSegments = [];
        $totalPrice = 0;

        for ($i = $fromStopIndex; $i < $toStopIndex; $i++) {
            if (isset($segments[$i])) {
                $relevantSegments[] = $segments[$i];
                $totalPrice += $segments[$i]->getPrice() * $seatsBooked;
            }
        }

        $booking = new Booking();
        $booking->setPassenger($passenger);
        $booking->setTrip($trip);
        $booking->setBoardingStop($boardingStop);
        $booking->setAlightingStop($alightingStop);
        $booking->setSeatsBooked($seatsBooked);
        $booking->setTotalPrice($totalPrice);
        $booking->setStatus($status);
        $booking->setCreatedAt($createdAt);

        $manager->persist($booking);

        // Attach BookingSegment and DECREMENT availableSeats on every traversed segment
        foreach ($relevantSegments as $segment) {
            $bookingSegment = new BookingSegment();
            $bookingSegment->setBooking($booking);
            $bookingSegment->setSegment($segment);
            $bookingSegment->setSeatsCount($seatsBooked);

            $manager->persist($bookingSegment);
            $booking->getBookingSegments()->add($bookingSegment);

            // Crucial Business Invariant: decrement availableSeats for CONFIRMED bookings!
            if ($status === BookingStatus::CONFIRMED) {
                $newAvailable = max(0, $segment->getAvailableSeats() - $seatsBooked);
                $segment->setAvailableSeats($newAvailable);
            }
        }

        return $booking;
    }

    /**
     * Creates a review for a completed trip.
     */
    private function createReview(
        ObjectManager $manager,
        Trip $trip,
        User $author,
        User $target,
        int $rating,
        string $comment,
        \DateTimeImmutable $createdAt
    ): Review {
        $review = new Review();
        $review->setTrip($trip);
        $review->setAuthor($author);
        $review->setTarget($target);
        $review->setRating($rating);
        $review->setComment($comment);
        $review->setCreatedAt($createdAt);

        $manager->persist($review);

        return $review;
    }
}
