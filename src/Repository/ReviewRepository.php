<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Checks if the author has already submitted a review for target on a given trip.
     */
    public function hasAlreadyReviewed(User $author, User $target, Trip $trip): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.author = :author')
            ->andWhere('r.target = :target')
            ->andWhere('r.trip = :trip')
            ->setParameter('author', $author)
            ->setParameter('target', $target)
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Returns ['average' => float|null, 'count' => int] in a single optimized SQL query.
     *
     * @return array{average: ?float, count: int}
     */
    public function getRatingStatsForUser(User $user): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS avgRating, COUNT(r.id) AS totalCount')
            ->where('r.target = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        $count = (int) $result['totalCount'];

        return [
            'average' => $count > 0 ? round((float) $result['avgRating'], 1) : null,
            'count' => $count,
        ];
    }

    /**
     * Liste des avis reçus pour l'écran S08 (avec jointure auteur/trajet et villes sans problème N+1).
     *
     * @return Review[]
     */
    public function findReceivedByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('a', 't', 'dc', 'ac')
            ->join('r.author', 'a')
            ->join('r.trip', 't')
            ->join('t.departureCity', 'dc')
            ->join('t.arrivalCity', 'ac')
            ->where('r.target = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns average star rating (e.g. 4.75) for a user.
     */
    public function getAverageRatingForUser(User $target): ?float
    {
        $avg = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.target = :target')
            ->setParameter('target', $target)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    /**
     * Returns total number of reviews received by a user.
     */
    public function getReviewCountForUser(User $target): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.target = :target')
            ->setParameter('target', $target)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
