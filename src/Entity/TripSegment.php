<?php

namespace App\Entity;

use App\Repository\TripSegmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripSegmentRepository::class)]
class TripSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'segments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(inversedBy: 'departureSegments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TripStop $fromStop = null;

    #[ORM\ManyToOne(inversedBy: 'arrivalSegments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TripStop $toStop = null;

    #[ORM\Column]
    private ?int $segmentOrder = null;

    #[ORM\Column]
    private ?int $availableSeats = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    private ?float $distanceKm = null;

    /**
     * @var Collection<int, BookingSegment>
     */
    #[ORM\OneToMany(targetEntity: BookingSegment::class, mappedBy: 'segment')]
    private Collection $bookingSegments;

    public function __construct()
    {
        $this->bookingSegments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFromStop(): ?TripStop
    {
        return $this->fromStop;
    }

    public function setFromStop(?TripStop $fromStop): static
    {
        $this->fromStop = $fromStop;

        return $this;
    }

    public function getToStop(): ?TripStop
    {
        return $this->toStop;
    }

    public function setToStop(?TripStop $toStop): static
    {
        $this->toStop = $toStop;

        return $this;
    }

    public function getSegmentOrder(): ?int
    {
        return $this->segmentOrder;
    }

    public function setSegmentOrder(int $segmentOrder): static
    {
        $this->segmentOrder = $segmentOrder;

        return $this;
    }

    public function getAvailableSeats(): ?int
    {
        return $this->availableSeats;
    }

    public function setAvailableSeats(int $availableSeats): static
    {
        $this->availableSeats = $availableSeats;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDistanceKm(): ?float
    {
        return $this->distanceKm;
    }

    public function setDistanceKm(?float $distanceKm): static
    {
        $this->distanceKm = $distanceKm;

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
            $bookingSegment->setSegment($this);
        }

        return $this;
    }

    public function removeBookingSegment(BookingSegment $bookingSegment): static
    {
        if ($this->bookingSegments->removeElement($bookingSegment)) {
            // set the owning side to null (unless already changed)
            if ($bookingSegment->getSegment() === $this) {
                $bookingSegment->setSegment(null);
            }
        }

        return $this;
    }
}
