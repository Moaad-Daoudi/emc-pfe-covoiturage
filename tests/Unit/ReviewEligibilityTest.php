<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Enum\TripStatus;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\ReviewNotAllowedException;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Service\ReviewManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests validating review eligibility invariants (T-204).
 */
final class ReviewEligibilityTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private BookingRepository $bookingRepository;
    private ReviewRepository $reviewRepository;
    private ReviewManager $reviewManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->bookingRepository = $this->createStub(BookingRepository::class);
        $this->reviewRepository = $this->createStub(ReviewRepository::class);

        $this->reviewManager = new ReviewManager(
            $this->entityManager,
            $this->bookingRepository,
            $this->reviewRepository
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setValue($entity, $id);
    }

    public function testPassengerCanReviewDriverOnCompletedTripWithConfirmedBooking(): void
    {
        $driver = new User();
        $this->setEntityId($driver, 1);

        $passenger = new User();
        $this->setEntityId($passenger, 2);

        $trip = (new Trip())->setDriver($driver)->setStatus(TripStatus::COMPLETED);
        $this->setEntityId($trip, 10);

        $this->bookingRepository
            ->method('hasConfirmedBooking')
            ->willReturn(true);

        $this->reviewRepository
            ->method('hasAlreadyReviewed')
            ->willReturn(false);

        $this->assertTrue($this->reviewManager->canReview($passenger, $driver, $trip));
    }

    public function testDriverCanReviewConfirmedPassengerOnCompletedTrip(): void
    {
        $driver = new User();
        $this->setEntityId($driver, 1);

        $passenger = new User();
        $this->setEntityId($passenger, 2);

        $trip = (new Trip())->setDriver($driver)->setStatus(TripStatus::COMPLETED);
        $this->setEntityId($trip, 10);

        $this->bookingRepository
            ->method('hasConfirmedBooking')
            ->willReturn(true);

        $this->reviewRepository
            ->method('hasAlreadyReviewed')
            ->willReturn(false);

        $this->assertTrue($this->reviewManager->canReview($driver, $passenger, $trip));
    }

    public function testSelfReviewIsForbidden(): void
    {
        $user = new User();
        $this->setEntityId($user, 1);

        $trip = (new Trip())->setDriver($user)->setStatus(TripStatus::COMPLETED);
        $this->setEntityId($trip, 10);

        $this->assertFalse($this->reviewManager->canReview($user, $user, $trip));
    }

    public function testReviewOnScheduledTripIsForbidden(): void
    {
        $driver = new User();
        $this->setEntityId($driver, 1);

        $passenger = new User();
        $this->setEntityId($passenger, 2);

        $trip = (new Trip())->setDriver($driver)->setStatus(TripStatus::SCHEDULED); // Not completed!
        $this->setEntityId($trip, 10);

        $this->assertFalse($this->reviewManager->canReview($passenger, $driver, $trip));
    }

    public function testPassengerCannotReviewAnotherPassenger(): void
    {
        $driver = new User();
        $this->setEntityId($driver, 1);

        $passenger1 = new User();
        $this->setEntityId($passenger1, 2);

        $passenger2 = new User();
        $this->setEntityId($passenger2, 3);

        $trip = (new Trip())->setDriver($driver)->setStatus(TripStatus::COMPLETED);
        $this->setEntityId($trip, 10);

        $this->assertFalse($this->reviewManager->canReview($passenger1, $passenger2, $trip));
    }

    public function testDuplicateReviewIsRejected(): void
    {
        $driver = new User();
        $this->setEntityId($driver, 1);

        $passenger = new User();
        $this->setEntityId($passenger, 2);

        $trip = (new Trip())->setDriver($driver)->setStatus(TripStatus::COMPLETED);
        $this->setEntityId($trip, 10);

        $this->bookingRepository
            ->method('hasConfirmedBooking')
            ->willReturn(true);

        // Already reviewed!
        $this->reviewRepository
            ->method('hasAlreadyReviewed')
            ->willReturn(true);

        $this->assertFalse($this->reviewManager->canReview($passenger, $driver, $trip));
    }

    public function testCreateReviewThrowsExceptionWhenRatingIsOutOfRange(): void
    {
        $driver = new User();
        $this->setEntityId($driver, 1);

        $passenger = new User();
        $this->setEntityId($passenger, 2);

        $trip = (new Trip())->setDriver($driver)->setStatus(TripStatus::COMPLETED);

        $this->expectException(ReviewNotAllowedException::class);
        $this->expectExceptionMessage('comprise entre 1 et 5');

        $this->reviewManager->createReview($passenger, $driver, $trip, 6);
    }
}
