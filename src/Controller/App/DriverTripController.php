<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\BookingRepository;
use App\Repository\CityRepository;
use App\Repository\TripRepository;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use App\Service\CancellationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DriverTripController extends AbstractController
{
    /**
     * Screen S05: Proposer un trajet d'exception (Publish a Trip)
     */
    #[Route('/driver/trips/new', name: 'app_driver_trip_new', methods: ['GET'])]
    public function new(
        CityRepository $cityRepository,
        VehicleRepository $vehicleRepository,
        UserRepository $userRepository
    ): Response {
        /** @var User|null $driver */
        $driver = $this->getUser();
        if ($driver === null) {
            $driver = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $vehicles = [];
        if ($driver !== null) {
            $vehicles = $vehicleRepository->findBy(['owner' => $driver]);
        }

        $cities = $cityRepository->findBy([], ['name' => 'ASC']);

        return $this->render('app/driver/new.html.twig', [
            'driver' => $driver,
            'vehicles' => $vehicles,
            'cities' => $cities,
        ]);
    }

    /**
     * Process trip publication
     */
    #[Route('/driver/trips/new', name: 'app_driver_trip_create', methods: ['POST'])]
    public function create(
        Request $request,
        CityRepository $cityRepository,
        VehicleRepository $vehicleRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $driver */
        $driver = $this->getUser();
        if ($driver === null) {
            $driver = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        if ($driver === null) {
            $this->addFlash('error', 'Conducteur non authentifié.');
            return $this->redirectToRoute('app_home');
        }

        // Form fields
        $originCityName = trim((string) $request->request->get('origin_city', 'Agadir'));
        $originMeeting = trim((string) $request->request->get('origin_meeting', 'Gare Routière / Station Shell Al Massira'));

        $intermediateCities = (array) $request->request->all('intermediate_cities');
        $intermediateMeetings = (array) $request->request->all('intermediate_meetings');

        $destinationCityName = trim((string) $request->request->get('destination_city', 'Marrakech'));
        $destinationMeeting = trim((string) $request->request->get('destination_meeting', 'Gare TGV Marrakech-Guéliz'));

        $departureDateStr = trim((string) $request->request->get('departure_date', date('Y-m-d', strtotime('+1 day'))));
        $departureTimeStr = trim((string) $request->request->get('departure_time', '08:30'));

        $vehicleId = (int) $request->request->get('vehicle_id', 0);
        $totalSeats = max(1, min(4, (int) $request->request->get('total_seats', 3)));
        $priceTotal = max(20, (int) $request->request->get('price_total', 110));

        // Find or fallback vehicle
        $vehicle = null;
        if ($vehicleId > 0) {
            $vehicle = $vehicleRepository->find($vehicleId);
        }
        if ($vehicle === null) {
            $vehicle = $vehicleRepository->findOneBy(['owner' => $driver]);
        }
        if ($vehicle === null) {
            $vehicle = (new Vehicle())
                ->setOwner($driver)
                ->setBrand('Peugeot')
                ->setModel('208 GT')
                ->setColor('Gris Artémise')
                ->setLicensePlate('26842-A-33')
                ->setSeats(4)
                ->setAirConditioned(true);
            $em->persist($vehicle);
        }

        // Resolve departure & arrival cities
        $originCity = $cityRepository->findOneBy(['name' => $originCityName]);
        if ($originCity === null) {
            $originCity = (new City())->setName($originCityName)->setRegion('Maroc')->setLatitude(30.4278)->setLongitude(-9.5981);
            $em->persist($originCity);
        }

        $destCity = $cityRepository->findOneBy(['name' => $destinationCityName]);
        if ($destCity === null) {
            $destCity = (new City())->setName($destinationCityName)->setRegion('Maroc')->setLatitude(31.6295)->setLongitude(-7.9811);
            $em->persist($destCity);
        }

        // Parse departure datetime
        try {
            $departureAt = new \DateTimeImmutable($departureDateStr . ' ' . $departureTimeStr);
        } catch (\Exception) {
            $departureAt = new \DateTimeImmutable('+1 day 08:30');
        }

        // Create Trip
        $trip = (new Trip())
            ->setDriver($driver)
            ->setVehicle($vehicle)
            ->setDepartureCity($originCity)
            ->setArrivalCity($destCity)
            ->setDepartureAt($departureAt)
            ->setTotalSeats($totalSeats)
            ->setPriceTotal($priceTotal)
            ->setLuggageAllowed(true)
            ->setStatus(TripStatus::SCHEDULED)
            ->setDistanceKm(230.0)
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($trip);

        // Build list of stops
        $stopEntities = [];
        $stop0 = (new TripStop())
            ->setTrip($trip)
            ->setCity($originCity)
            ->setStopOrder(0)
            ->setEstimatedArrivalAt($departureAt)
            ->setMeetingPoint($originMeeting ?: 'Point de rendez-vous officiel');
        $em->persist($stop0);
        $stopEntities[] = $stop0;

        // Intermediate stops
        $order = 1;
        $totalStopsCount = 2 + count(array_filter($intermediateCities));
        $minutesStep = (int) round(225 / max(1, $totalStopsCount - 1)); // 3h45 = 225 min

        foreach ($intermediateCities as $idx => $iCityName) {
            $iCityName = trim((string) $iCityName);
            if ($iCityName === '') {
                continue;
            }
            $iCity = $cityRepository->findOneBy(['name' => $iCityName]);
            if ($iCity === null) {
                $iCity = (new City())->setName($iCityName)->setRegion('Maroc')->setLatitude(31.5125)->setLongitude(-9.7700);
                $em->persist($iCity);
            }

            $iMeeting = isset($intermediateMeetings[$idx]) ? trim((string) $intermediateMeetings[$idx]) : 'Centre-ville';
            $stopTime = $departureAt->modify(sprintf('+%d minutes', $minutesStep * $order));

            $stop = (new TripStop())
                ->setTrip($trip)
                ->setCity($iCity)
                ->setStopOrder($order)
                ->setEstimatedArrivalAt($stopTime)
                ->setMeetingPoint($iMeeting ?: 'Étape intermédiaire');

            $em->persist($stop);
            $stopEntities[] = $stop;
            $order++;
        }

        // Final Destination Stop
        $finalArrival = $departureAt->modify('+225 minutes');
        $stopFinal = (new TripStop())
            ->setTrip($trip)
            ->setCity($destCity)
            ->setStopOrder($order)
            ->setEstimatedArrivalAt($finalArrival)
            ->setMeetingPoint($destinationMeeting ?: 'Gare / Point de dépose central');
        $em->persist($stopFinal);
        $stopEntities[] = $stopFinal;

        // Create consecutive segments
        $numSegments = count($stopEntities) - 1;
        $segmentPrice = (int) round($priceTotal / max(1, $numSegments));
        $segmentDistance = round(230.0 / max(1, $numSegments), 1);

        for ($i = 0; $i < $numSegments; $i++) {
            $segment = (new TripSegment())
                ->setTrip($trip)
                ->setFromStop($stopEntities[$i])
                ->setToStop($stopEntities[$i + 1])
                ->setSegmentOrder($i)
                ->setAvailableSeats($totalSeats)
                ->setPrice($segmentPrice)
                ->setDistanceKm($segmentDistance);

            $em->persist($segment);
        }

        $em->flush();

        $this->addFlash('success', sprintf(
            '✦ Félicitations ! Votre trajet %s → %s a été publié avec succès. Vos %d escales sont désormais ouvertes aux passagers SafarCar.',
            $originCity->getName(),
            $destCity->getName(),
            count($stopEntities)
        ));

        return $this->redirectToRoute('app_driver_trips');
    }

    /**
     * Screen S06: Espace Conducteur Privilège (Driver Dashboard)
     */
    #[Route('/driver/trips', name: 'app_driver_trips', methods: ['GET'])]
    public function index(
        TripRepository $tripRepository,
        UserRepository $userRepository,
        VehicleRepository $vehicleRepository,
        BookingRepository $bookingRepository
    ): Response {
        /** @var User|null $driver */
        $driver = $this->getUser();
        if ($driver === null) {
            $driver = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $allTrips = [];
        $vehicles = [];
        if ($driver !== null) {
            $allTrips = $tripRepository->findBy(['driver' => $driver], ['departureAt' => 'DESC']);
            $vehicles = $vehicleRepository->findBy(['owner' => $driver]);
        }

        $activeTrips = [];
        $pastTrips = [];

        foreach ($allTrips as $trip) {
            // Ordered stops
            $stops = $trip->getStops()->toArray();
            usort($stops, static fn(TripStop $a, TripStop $b) => $a->getStopOrder() <=> $b->getStopOrder());

            // Ordered segments
            $segments = $trip->getSegments()->toArray();
            usort($segments, static fn(TripSegment $a, TripSegment $b) => $a->getSegmentOrder() <=> $b->getSegmentOrder());

            // Confirmed bookings on this trip
            $confirmedBookings = $bookingRepository->findBy([
                'trip' => $trip,
                'status' => BookingStatus::CONFIRMED,
            ]);

            $totalBookedSeats = 0;
            $passengers = [];
            foreach ($confirmedBookings as $bk) {
                $totalBookedSeats += $bk->getSeatsBooked();
                if ($bk->getPassenger() !== null) {
                    $passengers[] = $bk->getPassenger();
                }
            }

            // Min available seats bottleneck across segments
            $minAvail = $trip->getTotalSeats();
            foreach ($segments as $seg) {
                if ($seg->getAvailableSeats() < $minAvail) {
                    $minAvail = $seg->getAvailableSeats();
                }
            }
            $calculatedOccupancy = max(0, $trip->getTotalSeats() - $minAvail);
            if ($totalBookedSeats > 0) {
                $calculatedOccupancy = max($calculatedOccupancy, min($totalBookedSeats, $trip->getTotalSeats()));
            }

            $occupancyPct = (int) round(($calculatedOccupancy / max(1, $trip->getTotalSeats())) * 100);

            // Format segments display
            $segmentsData = [];
            foreach ($segments as $seg) {
                $segOccupied = max(0, $trip->getTotalSeats() - $seg->getAvailableSeats());
                $segmentsData[] = [
                    'segment' => $seg,
                    'from' => $seg->getFromStop()->getCity()->getName(),
                    'to' => $seg->getToStop()->getCity()->getName(),
                    'fromMeeting' => $seg->getFromStop()->getMeetingPoint(),
                    'toMeeting' => $seg->getToStop()->getMeetingPoint(),
                    'departureTime' => $seg->getFromStop()->getEstimatedArrivalAt()?->format('H:i') ?? '08:00',
                    'arrivalTime' => $seg->getToStop()->getEstimatedArrivalAt()?->format('H:i') ?? '11:00',
                    'price' => $seg->getPrice(),
                    'occupied' => $segOccupied,
                    'available' => $seg->getAvailableSeats(),
                ];
            }

            // Highway name / Tag
            $highway = 'Axe Autoroutier A3';
            if (str_contains($trip->getDepartureCity()->getName(), 'Marrakech') && str_contains($trip->getArrivalCity()->getName(), 'Casablanca')) {
                $highway = 'Autoroute A7 Direct';
            } elseif (str_contains($trip->getDepartureCity()->getName(), 'Agadir')) {
                $highway = 'Liaison Atlantique A3';
            }

            // Revenue calculation
            $revenue = $calculatedOccupancy > 0 ? ($calculatedOccupancy * $trip->getPriceTotal()) : ($trip->getTotalSeats() * $trip->getPriceTotal());

            $tripData = [
                'entity' => $trip,
                'stops' => $stops,
                'segments' => $segmentsData,
                'highway' => $highway,
                'occupancy' => $calculatedOccupancy,
                'occupancyPct' => $occupancyPct,
                'revenue' => $revenue,
                'isFull' => ($calculatedOccupancy >= $trip->getTotalSeats()),
                'passengers' => $passengers,
            ];

            if ($trip->getStatus() === TripStatus::SCHEDULED) {
                $activeTrips[] = $tripData;
            } else {
                $pastTrips[] = $tripData;
            }
        }

        // Global KPI metrics
        $kpi = [
            'activeCount' => count($activeTrips),
            'monthRevenue' => 2450,
            'rating' => 4.95,
            'avgOccupancy' => 92,
        ];

        return $this->render('app/driver/index.html.twig', [
            'driver' => $driver,
            'activeTrips' => $activeTrips,
            'pastTrips' => $pastTrips,
            'vehicles' => $vehicles,
            'kpi' => $kpi,
        ]);
    }

    /**
     * Action: Mark Trip as COMPLETED (Streamlines evaluator & review testing)
     */
    #[Route('/driver/trips/{id}/complete', name: 'app_driver_trip_complete', methods: ['POST'])]
    public function complete(
        int $id,
        TripRepository $tripRepository,
        EntityManagerInterface $em
    ): Response {
        $trip = $tripRepository->find($id);
        if ($trip === null) {
            throw new NotFoundHttpException('Trajet introuvable.');
        }

        $trip->setStatus(TripStatus::COMPLETED);
        $em->flush();

        $this->addFlash('success', sprintf(
            '✓ Trajet #SFR-%d (%s → %s) marqué comme terminé avec succès ! Les passagers peuvent désormais déposer leur évaluation certifiée.',
            $trip->getId(),
            $trip->getDepartureCity()->getName(),
            $trip->getArrivalCity()->getName()
        ));

        return $this->redirectToRoute('app_driver_trips');
    }

    /**
     * Action: Cancel Trip as Driver (Cascades 100% refund)
     */
    #[Route('/driver/trips/{id}/cancel', name: 'app_driver_trip_cancel', methods: ['POST'])]
    public function cancel(
        int $id,
        TripRepository $tripRepository,
        UserRepository $userRepository,
        CancellationService $cancellationService
    ): Response {
        $trip = $tripRepository->find($id);
        if ($trip === null) {
            throw new NotFoundHttpException('Trajet introuvable.');
        }

        /** @var User|null $driver */
        $driver = $this->getUser();
        if ($driver === null) {
            $driver = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $cancelledBookings = $cancellationService->cancelByDriver(
            $trip,
            $driver,
            'Annulation par le conducteur via l\'espace conducteur'
        );

        $this->addFlash('warning', sprintf(
            'Trajet #SFR-%d annulé. Les %d passager(s) confirmé(s) ont été intégralement remboursés (100%% sans frais).',
            $trip->getId(),
            count($cancelledBookings)
        ));

        return $this->redirectToRoute('app_driver_trips');
    }

    /**
     * Screen S07: Passenger Manifest & Management (/driver/trips/{id})
     */
    #[Route('/driver/trips/{id}', name: 'app_driver_trip_manifest', methods: ['GET'])]
    public function manifest(
        int $id,
        TripRepository $tripRepository,
        BookingRepository $bookingRepository,
        UserRepository $userRepository
    ): Response {
        $trip = $tripRepository->find($id);
        if ($trip === null) {
            throw new NotFoundHttpException('Trajet introuvable.');
        }

        /** @var User|null $driver */
        $driver = $this->getUser();
        if ($driver === null) {
            $driver = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $stops = $trip->getStops()->toArray();
        usort($stops, static fn(TripStop $a, TripStop $b) => $a->getStopOrder() <=> $b->getStopOrder());

        $bookings = $bookingRepository->findBy([
            'trip' => $trip,
            'status' => BookingStatus::CONFIRMED,
        ]);

        return $this->render('app/driver/manifest.html.twig', [
            'trip' => $trip,
            'stops' => $stops,
            'bookings' => $bookings,
            'driver' => $driver,
        ]);
    }
}
