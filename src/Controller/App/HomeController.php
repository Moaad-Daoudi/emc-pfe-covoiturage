<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Repository\CityRepository;
use App\Service\TripSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        TripSearchService $tripSearchService,
        CityRepository $cityRepository
    ): Response {
        $origin = $request->query->get('origin', 'Agadir');
        $destination = $request->query->get('destination', 'Marrakech');
        $date = $request->query->get('date');
        $seats = (int) $request->query->get('seats', 1);
        $maxPrice = $request->query->get('max_price') ? (int) $request->query->get('max_price') : null;
        $direct = $request->query->getBoolean('direct');
        $ac = $request->query->getBoolean('ac');
        $luggage = $request->query->getBoolean('luggage');
        $sort = $request->query->get('sort', 'earliest');
        $timeSlots = (array) $request->query->all('time_slots');

        $criteria = [
            'origin' => $origin,
            'destination' => $destination,
            'date' => $date,
            'seats' => $seats,
            'max_price' => $maxPrice,
            'direct' => $direct,
            'ac' => $ac,
            'luggage' => $luggage,
            'sort' => $sort,
            'time_slots' => $timeSlots,
        ];

        $results = $tripSearchService->search($criteria);

        // If no results for the specific query, fallback to all available upcoming departures
        if (empty($results) && ($origin !== '' || $destination !== '')) {
            $fallbackResults = $tripSearchService->search([]);
        } else {
            $fallbackResults = [];
        }

        $allCities = $cityRepository->findAllOrderedByName();

        $popularPills = [
            ['from' => 'Agadir', 'to' => 'Marrakech', 'price' => 110],
            ['from' => 'Marrakech', 'to' => 'Casablanca', 'price' => 120],
            ['from' => 'Essaouira', 'to' => 'Marrakech', 'price' => 70],
            ['from' => 'Casablanca', 'to' => 'Rabat', 'price' => 45],
        ];

        return $this->render('app/home/index.html.twig', [
            'results' => $results,
            'fallbackResults' => $fallbackResults,
            'allCities' => $allCities,
            'popularPills' => $popularPills,
            'criteria' => $criteria,
        ]);
    }
}
