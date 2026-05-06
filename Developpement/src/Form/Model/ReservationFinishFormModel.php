<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dernière étape du parcours public : coordonnées client (la table est attribuée automatiquement).
 */
class ReservationFinishFormModel
{
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
