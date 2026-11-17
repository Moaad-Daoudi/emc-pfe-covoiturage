<?php

declare(strict_types=1);

use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Exception\SeatsNotAvailableException;
use App\Kernel;
use App\Service\RideBookingManager;
use Doctrine\ORM\EntityManagerInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[$_, $passengerId, $tripId, $boardingStopId, $alightingStopId] = $argv;

$kernel = new Kernel('test', false);
$kernel->boot();

$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine.orm.entity_manager');
/** @var \App\Repository\TripSegmentRepository $segmentRepo */
$segmentRepo = $em->getRepository(TripSegment::class);
$bookingManager = new RideBookingManager($em, $segmentRepo);

$passenger = $em->find(User::class, (int) $passengerId);
$trip = $em->find(Trip::class, (int) $tripId);
$from = $em->find(TripStop::class, (int) $boardingStopId);
$to = $em->find(TripStop::class, (int) $alightingStopId);

try {
    $bookingManager->book($passenger, $trip, $from, $to, 1);
    echo "BOOKED\n";
    exit(0);
} catch (SeatsNotAvailableException $e) {
    echo "REJECTED\n";
    exit(2);
}
