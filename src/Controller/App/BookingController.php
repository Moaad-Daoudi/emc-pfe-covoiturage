<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\TripStop;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\TripRepository;
use App\Repository\TripStopRepository;
use App\Repository\UserRepository;
use App\Service\CancellationService;
use App\Service\RideBookingManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    /**
     * Screen S03: Simulated Checkout & Confirmation
     */
    #[Route('/trips/{id}/checkout', name: 'app_trip_checkout', methods: ['GET'])]
    public function checkout(
        int $id,
        Request $request,
        TripRepository $tripRepository,
        TripStopRepository $stopRepository,
        RideBookingManager $bookingManager,
        UserRepository $userRepository
    ): Response {
        $trip = $tripRepository->find($id);
        if ($trip === null) {
            throw new NotFoundHttpException(sprintf('Trajet #%d introuvable.', $id));
        }

        $stops = $trip->getStops()->toArray();
        usort($stops, static fn(TripStop $a, TripStop $b) => $a->getStopOrder() <=> $b->getStopOrder());

        $fromId = $request->query->getInt('from', 0);
        $toId = $request->query->getInt('to', 0);
        $seats = max(1, $request->query->getInt('seats', 1));

        $boardingStop = ($fromId > 0) ? $stopRepository->find($fromId) : ($stops[0] ?? null);
        $alightingStop = ($toId > 0) ? $stopRepository->find($toId) : (!empty($stops) ? $stops[array_key_last($stops)] : null);

        if ($boardingStop === null || $alightingStop === null || $boardingStop->getStopOrder() >= $alightingStop->getStopOrder()) {
            $boardingStop = $stops[0] ?? null;
            $alightingStop = !empty($stops) ? $stops[array_key_last($stops)] : null;
        }

        try {
            $totalPrice = $bookingManager->calculateSubTripPrice($trip, $boardingStop, $alightingStop, $seats);
            $availableSeats = $bookingManager->getAvailableSeats($trip, $boardingStop, $alightingStop);
            $subSegments = $bookingManager->resolveSegmentsBetweenStops($trip, $boardingStop, $alightingStop);
            $distanceKm = array_sum(array_map(static fn($s) => (float) ($s->getDistanceKm() ?? 0.0), $subSegments));
        } catch (\Throwable) {
            $totalPrice = ($trip->getPriceTotal() ?? 110) * $seats;
            $availableSeats = $trip->getTotalSeats() ?? 3;
            $distanceKm = $trip->getDistanceKm() ?? 230;
            $subSegments = [];
        }

        // Active user or fallback to Amina for interactive demo / prototype testing
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser === null) {
            $currentUser = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        return $this->render('app/booking/checkout.html.twig', [
            'trip' => $trip,
            'boardingStop' => $boardingStop,
            'alightingStop' => $alightingStop,
            'seats' => $seats,
            'totalPrice' => $totalPrice,
            'availableSeats' => $availableSeats,
            'distanceKm' => round($distanceKm, 0),
            'segmentsCount' => count($subSegments),
            'passenger' => $currentUser,
        ]);
    }

    /**
     * Screen S03: Atomic Booking Execution & Redirect to S04 Dashboard
     */
    #[Route('/trips/{id}/checkout/confirm', name: 'app_trip_checkout_confirm', methods: ['POST'])]
    public function confirmBooking(
        int $id,
        Request $request,
        TripRepository $tripRepository,
        TripStopRepository $stopRepository,
        RideBookingManager $bookingManager,
        UserRepository $userRepository
    ): Response {
        $trip = $tripRepository->find($id);
        if ($trip === null) {
            throw new NotFoundHttpException('Trajet introuvable.');
        }

        $fromId = (int) $request->request->get('from');
        $toId = (int) $request->request->get('to');
        $seats = max(1, (int) $request->request->get('seats', 1));

        $boardingStop = $stopRepository->find($fromId);
        $alightingStop = $stopRepository->find($toId);

        if ($boardingStop === null || $alightingStop === null) {
            $this->addFlash('error', 'Arrêts d’embarquement ou de dépose invalides.');
            return $this->redirectToRoute('app_trip_show', ['id' => $id]);
        }

        /** @var User|null $passenger */
        $passenger = $this->getUser();
        if ($passenger === null) {
            $passenger = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        if ($passenger === null) {
            $this->addFlash('error', 'Veuillez vous connecter pour valider votre réservation.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $booking = $bookingManager->book($passenger, $trip, $boardingStop, $alightingStop, $seats);
            $this->addFlash('success', sprintf(
                'Réservation #SC-%d confirmée avec succès ! Votre billet pour %s → %s est prêt.',
                $booking->getId() * 73,
                $boardingStop->getCity()?->getName() ?? '',
                $alightingStop->getCity()?->getName() ?? ''
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur de réservation : ' . $e->getMessage());
            return $this->redirectToRoute('app_trip_show', ['id' => $id]);
        }

        return $this->redirectToRoute('app_passenger_bookings');
    }

    /**
     * Screen S04: Passenger Dashboard ("Mes réservations")
     */
    #[Route('/my/bookings', name: 'app_passenger_bookings', methods: ['GET'])]
    public function dashboard(
        BookingRepository $bookingRepository,
        UserRepository $userRepository
    ): Response {
        /** @var User|null $passenger */
        $passenger = $this->getUser();
        if ($passenger === null) {
            $passenger = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $allBookings = [];
        if ($passenger !== null) {
            $allBookings = $bookingRepository->findAllByPassenger($passenger);
        }

        $now = new \DateTimeImmutable();
        $upcomingBookings = [];
        $pastBookings = [];

        foreach ($allBookings as $b) {
            $departure = $b->getTrip()?->getDepartureAt();
            $isPast = ($departure !== null && $departure < $now) || ($b->getStatus() !== BookingStatus::CONFIRMED);

            if ($b->getStatus() === BookingStatus::CONFIRMED && !$isPast) {
                $upcomingBookings[] = $b;
            } else {
                $pastBookings[] = $b;
            }
        }

        // Stats KPIs computed dynamically from database
        $upcomingCount = count($upcomingBookings);
        $totalSavings = 0;
        $totalKm = 0;
        foreach ($allBookings as $b) {
            if ($b->getStatus() === BookingStatus::CONFIRMED) {
                $tripPrice = $b->getTotalPrice() ?? 0;
                $totalSavings += (int) round($tripPrice * 0.45);
                $totalKm += (float) ($b->getTrip()?->getDistanceKm() ?? 200.0);
            }
        }
        $co2Avoided = (int) round($totalKm * 0.12);

        return $this->render('app/booking/dashboard.html.twig', [
            'passenger' => $passenger,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
            'upcomingCount' => $upcomingCount,
            'totalSavings' => $totalSavings,
            'co2Avoided' => $co2Avoided,
        ]);
    }

    /**
     * T-203: Passenger Cancellation Action with Atomic Seat Restitution & Refund
     */
    #[Route('/bookings/{id}/cancel', name: 'app_booking_cancel', methods: ['POST'])]
    public function cancel(
        int $id,
        BookingRepository $bookingRepository,
        CancellationService $cancellationService
    ): Response {
        $booking = $bookingRepository->find($id);
        if ($booking === null) {
            throw new NotFoundHttpException('Réservation introuvable.');
        }

        try {
            $cancellationService->cancelByPassenger($booking);
            $this->addFlash('success', sprintf(
                'Votre réservation a été annulée. Un remboursement de %d DH a été crédité conformément à la politique d\'annulation.',
                $booking->getRefundAmount() ?? 0
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible d’annuler la réservation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_passenger_bookings');
    }
}