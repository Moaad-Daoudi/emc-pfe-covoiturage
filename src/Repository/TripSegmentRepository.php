<?php

namespace App\Repository;

use App\Entity\Trip;
use App\Entity\TripSegment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class TripSegmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TripSegment::class);
    }

    /**
     * Retourne les segments ordonnés pour le sous-trajet [startOrder, endOrder).
     *
     * @return TripSegment[]
     */
    public function findBetweenStopOrders(
        Trip $trip,
        int $startOrder,
        int $endOrder,
        bool $lockForUpdate = false,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->where('s.trip = :trip')
            ->andWhere('s.segmentOrder >= :startOrder')
            ->andWhere('s.segmentOrder < :endOrder')
            ->setParameter('trip', $trip)
            ->setParameter('startOrder', $startOrder)
            ->setParameter('endOrder', $endOrder)
            ->orderBy('s.segmentOrder', 'ASC');

        $query = $qb->getQuery();

        if ($lockForUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
            // Force Doctrine à écraser l'Identity Map avec les données fraîches verrouillées en BDD
            $query->setHint(Query::HINT_REFRESH, true);
        }

        return $query->getResult();
    }

    /**
     * Alias method for finding segments between two stop orders.
     *
     * @return TripSegment[]
     */
    public function findSegmentsForPath(Trip $trip, int $fromOrder, int $toOrder, bool $lockForUpdate = false): array
    {
        return $this->findBetweenStopOrders($trip, $fromOrder, $toOrder, $lockForUpdate);
    }
}
