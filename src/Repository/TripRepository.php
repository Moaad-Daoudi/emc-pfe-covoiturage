<?php

namespace App\Repository;

use App\Domain\Enum\TripStatus;
use App\Entity\Trip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    /**
     * @return Trip[]
     */
    public function findScheduledTripsWithDetails(): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('s', 'seg', 'd', 'v', 'dc', 'ac', 'sc')
            ->leftJoin('t.stops', 's')
            ->leftJoin('s.city', 'sc')
            ->leftJoin('t.segments', 'seg')
            ->leftJoin('t.driver', 'd')
            ->leftJoin('t.vehicle', 'v')
            ->leftJoin('t.departureCity', 'dc')
            ->leftJoin('t.arrivalCity', 'ac')
            ->where('t.status = :status')
            ->setParameter('status', TripStatus::SCHEDULED)
            ->orderBy('t.departureAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
