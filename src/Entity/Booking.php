<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Domain\Enum\BookingStatus;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $passenger = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(inversedBy: 'boardingBookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TripStop $boardingStop = null;

    #[ORM\ManyToOne(inversedBy: 'alightingBookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TripStop $alightingStop = null;

    #[ORM\Column]
    private ?int $seatsBooked = null;

    #[ORM\Column]
    private ?int $totalPrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $refundAmount = null;

    #[ORM\Column(length: 30, enumType: BookingStatus::class)]
    private ?BookingStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * @var Collection<int, BookingSegment>
     */
    #[ORM\OneToMany(targetEntity: BookingSegment::class, mappedBy: 'booking')]
    private Collection $bookingSegments;

    public function __construct()
    {
        $this->bookingSegments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPassenger(): ?User
    {
        return $this->passenger;
    }

    public function setPassenger(?User $passenger): static
    {
        $this->passenger = $passenger;

        return $this;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function getBoardingStop(): ?TripStop
    {
        return $this->boardingStop;
    }

    public function setBoardingStop(?TripStop $boardingStop): static
    {
        $this->boardingStop = $boardingStop;

        return $this;
    }

    public function getAlightingStop(): ?TripStop
    {
        return $this->alightingStop;
    }

    public function setAlightingStop(?TripStop $alightingStop): static
    {
        $this->alightingStop = $alightingStop;

        return $this;
    }

    public function getSeatsBooked(): ?int
    {
        return $this->seatsBooked;
    }

    public function setSeatsBooked(int $seatsBooked): static
    {
        $this->seatsBooked = $seatsBooked;

        return $this;
    }

    public function getTotalPrice(): ?int
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(int $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getRefundAmount(): ?int
    {
        return $this->refundAmount;
    }

    public function setRefundAmount(?int $refundAmount): static
    {
        $this->refundAmount = $refundAmount;

        return $this;
    }

    public function getStatus(): ?BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    /**
     * @return Collection<int, BookingSegment>
     */
    public function getBookingSegments(): Collection
    {
        return $this->bookingSegments;
    }

    public function addBookingSegment(BookingSegment $bookingSegment): static
    {
        if (!$this->bookingSegments->contains($bookingSegment)) {
            $this->bookingSegments->add($bookingSegment);
            $bookingSegment->setBooking($this);
        }

        return $this;
    }

    public function removeBookingSegment(BookingSegment $bookingSegment): static
    {
        if ($this->bookingSegments->removeElement($bookingSegment)) {
            // set the owning side to null (unless already changed)
            if ($bookingSegment->getBooking() === $this) {
                $bookingSegment->setBooking(null);
            }
        }

        return $this;
    }
}
