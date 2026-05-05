<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Restaurant;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données du formulaire public de réservation (non persistées telles quelles → {@see ReservationBookingRequest}).
 */
class ReservationBookingFormModel
{
    #[Assert\NotNull(message: 'Choisissez un restaurant.')]
    private ?Restaurant $restaurant = null;

    #[Assert\NotNull(message: 'Choisissez une date de réservation.')]
    private ?\DateTimeImmutable $reservationDate = null;

    #[Assert\NotBlank(message: 'Choisissez un créneau.')]
    private ?string $slotTime = null;

    #[Assert\NotBlank(message: "L'effectif est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de personnes doit être d'au moins 1.")]
    #[Assert\Range(max: 99)]
    private ?int $partySize = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 120)]
    private ?string $guestName = null;

    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email n'est pas valide.")]
    #[Assert\Length(max: 180)]
    private ?string $guestEmail = null;

    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Length(min: 8, max: 32)]
    private ?string $guestPhone = null;

    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function setRestaurant(?Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function getReservationDate(): ?\DateTimeImmutable
    {
        return $this->reservationDate;
    }

    public function setReservationDate(?\DateTimeImmutable $reservationDate): void
    {
        $this->reservationDate = $reservationDate;
    }

    public function getSlotTime(): ?string
    {
        return $this->slotTime;
    }

    public function setSlotTime(?string $slotTime): void
    {
        $this->slotTime = $slotTime;
    }

    public function getPartySize(): ?int
    {
        return $this->partySize;
    }

    public function setPartySize(?int $partySize): void
    {
        $this->partySize = $partySize;
    }

    public function getGuestName(): ?string
    {
        return $this->guestName;
    }

    public function setGuestName(?string $guestName): void
    {
        $this->guestName = $guestName;
    }

    public function getGuestEmail(): ?string
    {
        return $this->guestEmail;
    }

    public function setGuestEmail(?string $guestEmail): void
    {
        $this->guestEmail = $guestEmail;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function setGuestPhone(?string $guestPhone): void
    {
        $this->guestPhone = $guestPhone;
    }
}
