<?php

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $region = null;

    #[ORM\Column]
    private ?float $latitude = null;

    #[ORM\Column]
    private ?float $longitude = null;

    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'departureCity')]
    private Collection $departureCity;

    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'arrivalCity')]
    private Collection $arrivingTrips;

    /**
     * @var Collection<int, TripStop>
     */
    #[ORM\OneToMany(targetEntity: TripStop::class, mappedBy: 'city')]
    private Collection $tripStops;

    public function __construct()
    {
        $this->departureCity = new ArrayCollection();
        $this->arrivingTrips = new ArrayCollection();
        $this->tripStops = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getDepartureCity(): Collection
    {
        return $this->departureCity;
    }

    public function addDepartureCity(Trip $departureCity): static
    {
        if (!$this->departureCity->contains($departureCity)) {
            $this->departureCity->add($departureCity);
            $departureCity->setDepartureCity($this);
        }

        return $this;
    }

    public function removeDepartureCity(Trip $departureCity): static
    {
        if ($this->departureCity->removeElement($departureCity)) {
            // set the owning side to null (unless already changed)
            if ($departureCity->getDepartureCity() === $this) {
                $departureCity->setDepartureCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getArrivingTrips(): Collection
    {
        return $this->arrivingTrips;
    }

    public function addArrivingTrip(Trip $arrivingTrip): static
    {
        if (!$this->arrivingTrips->contains($arrivingTrip)) {
            $this->arrivingTrips->add($arrivingTrip);
            $arrivingTrip->setArrivalCity($this);
        }

        return $this;
    }

    public function removeArrivingTrip(Trip $arrivingTrip): static
    {
        if ($this->arrivingTrips->removeElement($arrivingTrip)) {
            // set the owning side to null (unless already changed)
            if ($arrivingTrip->getArrivalCity() === $this) {
                $arrivingTrip->setArrivalCity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TripStop>
     */
    public function getTripStops(): Collection
    {
        return $this->tripStops;
    }

    public function addTripStop(TripStop $tripStop): static
    {
        if (!$this->tripStops->contains($tripStop)) {
            $this->tripStops->add($tripStop);
            $tripStop->setCity($this);
        }

        return $this;
    }

    public function removeTripStop(TripStop $tripStop): static
    {
        if ($this->tripStops->removeElement($tripStop)) {
            // set the owning side to null (unless already changed)
            if ($tripStop->getCity() === $this) {
                $tripStop->setCity(null);
            }
        }

        return $this;
    }
}
