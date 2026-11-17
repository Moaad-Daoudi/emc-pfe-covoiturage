<?php

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Domain\Enum\TripStatus;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $driver = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\ManyToOne(inversedBy: 'departureCity')]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $departureCity = null;

    #[ORM\ManyToOne(inversedBy: 'arrivingTrips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?City $arrivalCity = null;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'passenger')]
    private Collection $bookings;

    #[ORM\Column]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column]
    private ?int $totalSeats = null;

    #[ORM\Column]
    private ?int $priceTotal = null;

    #[ORM\Column]
    private ?bool $luggageAllowed = null;

    #[ORM\Column(type: 'string', enumType: TripStatus::class)]
    private ?TripStatus $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $routeGeometry = null;

    #[ORM\Column(nullable: true)]
    private ?float $distanceKm = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, TripStop>
     */
    #[ORM\OneToMany(targetEntity: TripStop::class, mappedBy: 'trip')]
    private Collection $stops;

    /**
     * @var Collection<int, TripSegment>
     */
    #[ORM\OneToMany(targetEntity: TripSegment::class, mappedBy: 'trip')]
    private Collection $segments;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'trip')]
    private Collection $reviews;

    public function __construct()
    {
        $this->stops = new ArrayCollection();
        $this->segments = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getDepartureCity(): ?City
    {
        return $this->departureCity;
    }

    public function setDepartureCity(?City $departureCity): static
    {
        $this->departureCity = $departureCity;

        return $this;
    }

    public function getArrivalCity(): ?City
    {
        return $this->arrivalCity;
    }

    public function setArrivalCity(?City $arrivalCity): static
    {
        $this->arrivalCity = $arrivalCity;

        return $this;
    }

    public function getDepartureAt(): ?\DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(\DateTimeImmutable $departureAt): static
    {
        $this->departureAt = $departureAt;

        return $this;
    }

    public function getTotalSeats(): ?int
    {
        return $this->totalSeats;
    }

    public function setTotalSeats(int $totalSeats): static
    {
        $this->totalSeats = $totalSeats;

        return $this;
    }

    public function getPriceTotal(): ?int
    {
        return $this->priceTotal;
    }

    public function setPriceTotal(int $priceTotal): static
    {
        $this->priceTotal = $priceTotal;

        return $this;
    }

    public function isLuggageAllowed(): ?bool
    {
        return $this->luggageAllowed;
    }

    public function setLuggageAllowed(bool $luggageAllowed): static
    {
        $this->luggageAllowed = $luggageAllowed;

        return $this;
    }

    public function getStatus(): ?TripStatus
    {
        return $this->status;
    }

    public function setStatus(TripStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRouteGeometry(): ?string
    {
        return $this->routeGeometry;
    }

    public function setRouteGeometry(?string $routeGeometry): static
    {
        $this->routeGeometry = $routeGeometry;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->departureCity . ' → ' . $this->arrivalCity;
    }

    /**
     * @return Collection<int, TripStop>
     */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(TripStop $stop): static
    {
        if (!$this->stops->contains($stop)) {
            $this->stops->add($stop);
            $stop->setTrip($this);
        }

        return $this;
    }

    public function removeStop(TripStop $stop): static
    {
        if ($this->stops->removeElement($stop)) {
            // set the owning side to null (unless already changed)
            if ($stop->getTrip() === $this) {
                $stop->setTrip(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TripSegment>
     */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(TripSegment $segment): static
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
            $segment->setTrip($this);
        }

        return $this;
    }

    public function removeSegment(TripSegment $segment): static
    {
        if ($this->segments->removeElement($segment)) {
            // set the owning side to null (unless already changed)
            if ($segment->getTrip() === $this) {
                $segment->setTrip(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setTrip($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getTrip() === $this) {
                $review->setTrip(null);
            }
        }

        return $this;
    }
}
