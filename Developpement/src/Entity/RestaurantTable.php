<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RestaurantTableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Table physique en salle (numéro, capacité). Nom d'entité évite le mot réservé « Table » en PHP.
 *
 * Règles : occupation exclusive par créneau — SPECS_FONCTIONNELLES §6.4–6.5.
 */
#[ORM\Entity(repositoryClass: RestaurantTableRepository::class)]
#[ORM\Table(name: 'restaurant_table')]
#[ORM\UniqueConstraint(name: 'uniq_restaurant_table_number', fields: ['number'])]
class RestaurantTable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Le numéro de table est obligatoire.')]
    #[Assert\Length(max: 32)]
    private ?string $number = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Positive(message: 'La capacité doit être au moins 1.')]
    #[Assert\Range(max: 99, maxMessage: 'La capacité ne peut pas dépasser {{ max }}.')]
    private ?int $capacity = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'restaurantTable', orphanRemoval: false)]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setRestaurantTable($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);

        return $this;
    }
}
