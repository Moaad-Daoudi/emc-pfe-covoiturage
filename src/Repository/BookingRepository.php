<?php

namespace App\Repository;

use App\Domain\Enum\BookingStatus;
use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return Booking[]
     */
    public function findConfirmedByTrip(Trip $trip): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.trip = :trip')
            ->andWhere('b.status = :status')
            ->setParameter('trip', $trip)
            ->setParameter('status', BookingStatus::CONFIRMED)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Booking[]
     */
    public function findConfirmedByPassenger(User $passenger): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.passenger = :passenger')
            ->andWhere('b.status = :status')
            ->setParameter('passenger', $passenger)
            ->setParameter('status', BookingStatus::CONFIRMED)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
