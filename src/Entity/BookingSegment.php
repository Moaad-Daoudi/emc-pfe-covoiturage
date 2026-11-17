<?php

namespace App\Entity;

use App\Repository\BookingSegmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingSegmentRepository::class)]
class BookingSegment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookingSegments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(inversedBy: 'bookingSegments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TripSegment $segment = null;

    #[ORM\Column]
    private ?int $seatsCount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getSegment(): ?TripSegment
    {
        return $this->segment;
    }

    public function setSegment(?TripSegment $segment): static
    {
        $this->segment = $segment;

        return $this;
    }

    public function getSeatsCount(): ?int
    {
        return $this->seatsCount;
    }

    public function setSeatsCount(int $seatsCount): static
    {
        $this->seatsCount = $seatsCount;

        return $this;
    }
}
