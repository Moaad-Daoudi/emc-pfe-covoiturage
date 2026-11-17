<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Domain\Enum\TripStatus;
use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\CityRepository;
use App\Repository\TripRepository;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
            // Create default vehicle if none exists
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
        // Stop 0: Origin
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

        return $this->redirectToRoute('app_trip_show', ['id' => $trip->getId()]);
    }

    /**
     * Driver Dashboard (Screen S06 preview / list)
     */
    #[Route('/driver/trips', name: 'app_driver_trips', methods: ['GET'])]
    public function index(
        TripRepository $tripRepository,
        UserRepository $userRepository
    ): Response {
        /** @var User|null $driver */
        $driver = $this->getUser();
        if ($driver === null) {
            $driver = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $trips = [];
        if ($driver !== null) {
            $trips = $tripRepository->findBy(['driver' => $driver], ['departureAt' => 'DESC']);
        }

        return $this->render('app/driver/index.html.twig', [
            'driver' => $driver,
            'trips' => $trips,
        ]);
    }
}
