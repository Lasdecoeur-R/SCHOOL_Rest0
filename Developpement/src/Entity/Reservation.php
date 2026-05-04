<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réservation client (sans compte utilisateur) : coordonnées, créneau, table assignée, statut.
 *
 * Index composite pour le planning admin — SPECS_TECHNIQUES (planning par date / créneau).
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
#[ORM\Index(name: 'idx_reservation_planning', columns: ['reservation_date', 'slot_at', 'status'])]
class Reservation
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_CONFIRMED, self::STATUS_CANCELLED];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Une table doit être associée à la réservation.')]
    private ?RestaurantTable $restaurantTable = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date est obligatoire.')]
    private ?\DateTimeImmutable $reservationDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: "L'horaire du créneau est obligatoire.")]
    private ?\DateTimeImmutable $slotAt = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Positive(message: "Le nombre de personnes doit être d'au moins 1.")]
    #[Assert\Range(max: 99, maxMessage: 'Le nombre de personnes ne peut pas dépasser {{ max }}.')]
    private ?int $partySize = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le nom du client est obligatoire.')]
    #[Assert\Length(max: 120)]
    private ?string $guestName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'email du client est obligatoire.")]
    #[Assert\Email(message: "L'email du client n'est pas valide.")]
    private ?string $guestEmail = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Length(min: 8, max: 32)]
    private ?string $guestPhone = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::STATUSES, message: 'Statut de réservation invalide.')]
    private string $status = self::STATUS_CONFIRMED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRestaurantTable(): ?RestaurantTable
    {
        return $this->restaurantTable;
    }

    public function setRestaurantTable(?RestaurantTable $restaurantTable): static
    {
        $this->restaurantTable = $restaurantTable;

        return $this;
    }

    public function getReservationDate(): ?\DateTimeImmutable
    {
        return $this->reservationDate;
    }

    public function setReservationDate(\DateTimeImmutable $reservationDate): static
    {
        $this->reservationDate = $reservationDate;

        return $this;
    }

    public function getSlotAt(): ?\DateTimeImmutable
    {
        return $this->slotAt;
    }

    public function setSlotAt(\DateTimeImmutable $slotAt): static
    {
        $this->slotAt = $slotAt;

        return $this;
    }

    public function getPartySize(): ?int
    {
        return $this->partySize;
    }

    public function setPartySize(int $partySize): static
    {
        $this->partySize = $partySize;

        return $this;
    }

    public function getGuestName(): ?string
    {
        return $this->guestName;
    }

    public function setGuestName(string $guestName): static
    {
        $this->guestName = $guestName;

        return $this;
    }

    public function getGuestEmail(): ?string
    {
        return $this->guestEmail;
    }

    public function setGuestEmail(string $guestEmail): static
    {
        $this->guestEmail = $guestEmail;

        return $this;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function setGuestPhone(string $guestPhone): static
    {
        $this->guestPhone = $guestPhone;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return self::STATUS_CONFIRMED === $this->status;
    }
}
