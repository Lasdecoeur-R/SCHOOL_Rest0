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
 *
 * Clé {@see self::buildOccupancyKey()} : unicité logique « une table active par date + créneau »
 * (SPECS_TECHNIQUES §5) ; nulle si la réservation n’est pas confirmée.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
#[ORM\Index(name: 'idx_reservation_planning', columns: ['reservation_date', 'slot_at', 'status'])]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    /** Réservation encore valide pour le planning et l’occupation de table. */
    public const STATUS_CONFIRMED = 'confirmed';

    /** Réservation annulée : libère la clé d’occupation, n’empêche plus une nouvelle résa sur le créneau. */
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_CONFIRMED, self::STATUS_CANCELLED];

    /** Identifiant technique auto-généré par MySQL / SQLite. */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Table physique attribuée ; RESTRICT en suppression pour ne pas casser l’historique. */
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Une table doit être associée à la réservation.')]
    private ?RestaurantTable $restaurantTable = null;

    /** Jour calendaire de la réservation (sans heure). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date est obligatoire.')]
    private ?\DateTimeImmutable $reservationDate = null;

    /** Heure exacte du créneau (midi / soir selon les règles métier du parcours public). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: "L'horaire du créneau est obligatoire.")]
    private ?\DateTimeImmutable $slotAt = null;

    /** Effectif déclaré ; doit rester ≤ capacité de la table choisie par le service. */
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Positive(message: "Le nombre de personnes doit être d'au moins 1.")]
    #[Assert\Range(max: 99, maxMessage: 'Le nombre de personnes ne peut pas dépasser {{ max }}.')]
    private ?int $partySize = null;

    /** Nom du client (sans compte applicatif). */
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le nom du client est obligatoire.')]
    #[Assert\Length(max: 120)]
    private ?string $guestName = null;

    /** Email de contact (validation format). */
    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'email du client est obligatoire.")]
    #[Assert\Email(message: "L'email du client n'est pas valide.")]
    private ?string $guestEmail = null;

    /** Téléphone (formats variés, longueur bornée). */
    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Length(min: 8, max: 32)]
    private ?string $guestPhone = null;

    /** confirmed = occupe la table sur le créneau ; cancelled = libère la place logique. */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::STATUSES, message: 'Statut de réservation invalide.')]
    private string $status = self::STATUS_CONFIRMED;

    /**
     * Empreinte unique « table + date + créneau » pour les réservations confirmées (conflit concurrent).
     * Null si annulée : plusieurs lignes peuvent alors avoir occupancy_key null (index unique SQL OK).
     */
    #[ORM\Column(name: 'occupancy_key', length: 64, nullable: true, unique: true)]
    private ?string $occupancyKey = null;

    /** Horodatage de création de la ligne (audit / support). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /* -------------------------------------------------------------------------
       Accesseurs / mutateurs : chaque setter retourne $this (interface fluent).
       ------------------------------------------------------------------------- */

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

    public function getOccupancyKey(): ?string
    {
        return $this->occupancyKey;
    }

    /**
     * Empreinte stable pour une réservation confirmée (table + jour + horaire exact du créneau).
     */
    public static function buildOccupancyKey(
        int $restaurantTableId,
        \DateTimeImmutable $reservationDate,
        \DateTimeImmutable $slotAt,
    ): string {
        $payload = $restaurantTableId.'|'.$reservationDate->format('Y-m-d').'|'.$slotAt->format('Y-m-d H:i:s');

        return hash('sha256', $payload);
    }

    /**
     * Recalcule la clé d’occupation selon le statut et les champs courants (à appeler avant flush si besoin).
     */
    public function synchronizeOccupancyKey(): void
    {
        if (self::STATUS_CONFIRMED === $this->status
            && null !== $this->restaurantTable?->getId()
            && null !== $this->reservationDate
            && null !== $this->slotAt) {
            $this->occupancyKey = self::buildOccupancyKey(
                (int) $this->restaurantTable->getId(),
                $this->reservationDate,
                $this->slotAt,
            );

            return;
        }

        $this->occupancyKey = null;
    }

    /** Doctrine : avant première insertion en base. */
    #[ORM\PrePersist]
    public function onPrePersistSynchronizeOccupancyKey(): void
    {
        $this->synchronizeOccupancyKey();
    }

    /** Doctrine : avant UPDATE ; recalcule la clé si passage annulé ↔ confirmé. */
    #[ORM\PreUpdate]
    public function onPreUpdateSynchronizeOccupancyKey(): void
    {
        $this->synchronizeOccupancyKey();
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

    /** Indique si la réservation compte pour l’occupation d’une table sur un créneau. */
    public function isConfirmed(): bool
    {
        return self::STATUS_CONFIRMED === $this->status;
    }
}
