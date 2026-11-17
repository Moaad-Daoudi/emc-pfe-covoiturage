<?php

namespace App\Entity;

use App\Repository\TripStopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripStopRepository::class)]
#[ORM\Table(name: 'trip_stop')]
#[ORM\UniqueConstraint(name: 'uniq_trip_stop_order', columns: ['trip_id', 'stop_order'])]
class TripStop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stops')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(inversedBy: 'tripStops')]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $city = null;

    #[ORM\Column]
    private ?int $stopOrder = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $estimatedArrivalAt = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $meetingPoint = null;

    /**
     * @var Collection<int, TripSegment>
     */
    #[ORM\OneToMany(targetEntity: TripSegment::class, mappedBy: 'fromStop')]
    private Collection $departureSegments;

    /**
     * @var Collection<int, TripSegment>
     */
    #[ORM\OneToMany(targetEntity: TripSegment::class, mappedBy: 'toStop')]
    private Collection $arrivalSegments;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'boardingStop')]
    private Collection $boardingBookings;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'alightingStop')]
    private Collection $alightingBookings;

    public function __construct()
    {
        $this->departureSegments = new ArrayCollection();
        $this->arrivalSegments = new ArrayCollection();
        $this->boardingBookings = new ArrayCollection();
        $this->alightingBookings = new ArrayCollection();
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

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getStopOrder(): ?int
    {
        return $this->stopOrder;
    }

    public function setStopOrder(int $stopOrder): static
    {
        $this->stopOrder = $stopOrder;

        return $this;
    }

    public function getEstimatedArrivalAt(): ?\DateTimeImmutable
    {
        return $this->estimatedArrivalAt;
    }

    public function setEstimatedArrivalAt(\DateTimeImmutable $estimatedArrivalAt): static
    {
        $this->estimatedArrivalAt = $estimatedArrivalAt;

        return $this;
    }

    public function getMeetingPoint(): ?string
    {
        return $this->meetingPoint;
    }

    public function setMeetingPoint(?string $meetingPoint): static
    {
        $this->meetingPoint = $meetingPoint;

        return $this;
    }

    /**
     * @return Collection<int, TripSegment>
     */
    public function getDepartureSegments(): Collection
    {
        return $this->departureSegments;
    }

    public function addDepartureSegment(TripSegment $departureSegment): static
    {
        if (!$this->departureSegments->contains($departureSegment)) {
            $this->departureSegments->add($departureSegment);
            $departureSegment->setFromStop($this);
        }

        return $this;
    }

    public function removeDepartureSegment(TripSegment $departureSegment): static
    {
        if ($this->departureSegments->removeElement($departureSegment)) {
            // set the owning side to null (unless already changed)
            if ($departureSegment->getFromStop() === $this) {
                $departureSegment->setFromStop(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TripSegment>
     */
    public function getArrivalSegments(): Collection
    {
        return $this->arrivalSegments;
    }

    public function addArrivalSegment(TripSegment $arrivalSegment): static
    {
        if (!$this->arrivalSegments->contains($arrivalSegment)) {
            $this->arrivalSegments->add($arrivalSegment);
            $arrivalSegment->setToStop($this);
        }

        return $this;
    }

    public function removeArrivalSegment(TripSegment $arrivalSegment): static
    {
        if ($this->arrivalSegments->removeElement($arrivalSegment)) {
            // set the owning side to null (unless already changed)
            if ($arrivalSegment->getToStop() === $this) {
                $arrivalSegment->setToStop(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBoardingBookings(): Collection
    {
        return $this->boardingBookings;
    }

    public function addBoardingBooking(Booking $boardingBooking): static
    {
        if (!$this->boardingBookings->contains($boardingBooking)) {
            $this->boardingBookings->add($boardingBooking);
            $boardingBooking->setBoardingStop($this);
        }

        return $this;
    }

    public function removeBoardingBooking(Booking $boardingBooking): static
    {
        if ($this->boardingBookings->removeElement($boardingBooking)) {
            // set the owning side to null (unless already changed)
            if ($boardingBooking->getBoardingStop() === $this) {
                $boardingBooking->setBoardingStop(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getAlightingBookings(): Collection
    {
        return $this->alightingBookings;
    }

    public function addAlightingBooking(Booking $alightingBooking): static
    {
        if (!$this->alightingBookings->contains($alightingBooking)) {
            $this->alightingBookings->add($alightingBooking);
            $alightingBooking->setAlightingStop($this);
        }

        return $this;
    }

    public function removeAlightingBooking(Booking $alightingBooking): static
    {
        if ($this->alightingBookings->removeElement($alightingBooking)) {
            // set the owning side to null (unless already changed)
            if ($alightingBooking->getAlightingStop() === $this) {
                $alightingBooking->setAlightingStop(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (Arrêt #%d)', $this->city?->getName() ?? 'Arrêt', $this->stopOrder ?? 0);
    }
}
