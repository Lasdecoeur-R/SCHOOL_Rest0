<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RestaurantTableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Table physique en salle (numéro, capacité). Nom d'entité évite le mot réservé « Table » en PHP.
 *
 * Règles : occupation exclusive par créneau — SPECS_FONCTIONNELLES §6.4–6.5.
 */
#[ORM\Entity(repositoryClass: RestaurantTableRepository::class)]
#[ORM\Table(name: 'restaurant_table')]
#[ORM\UniqueConstraint(name: 'uniq_restaurant_table_place_number', fields: ['restaurant', 'number'])]
#[UniqueEntity(fields: ['number', 'restaurant'], message: 'Ce numéro de table existe déjà pour cet établissement.')]
class RestaurantTable
{
    /** Clé primaire (identifiant interne, distinct du numéro affiché en salle). */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Établissement auquel cette table physique appartient. */
    #[ORM\ManyToOne(inversedBy: 'tables')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le restaurant doit être précisé.')]
    private ?Restaurant $restaurant = null;

    /** Libellé métier (ex. « 12 », « Terrasse A ») ; unique dans l’établissement. */
    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Le numéro de table est obligatoire.')]
    #[Assert\Length(max: 32)]
    private ?string $number = null;

    /** Nombre maximum de couverts pour ce plan de table. */
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Positive(message: 'La capacité doit être au moins 1.')]
    #[Assert\Range(max: 99, maxMessage: 'La capacité ne peut pas dépasser {{ max }}.')]
    private ?int $capacity = null;

    /** Si false : la table n’est plus proposée par le service de réservation (sans effacer l’historique). */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /**
     * Réservations liées à cette table (inverse de {@see Reservation::$restaurantTable}).
     *
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'restaurantTable', orphanRemoval: false)]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    /* Accesseurs / mutateurs (fluent). */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function setRestaurant(?Restaurant $restaurant): static
    {
        $this->restaurant = $restaurant;

        return $this;
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

    /**
     * Maintient la cohérence bidirectionnelle Doctrine (collection + côté propriétaire).
     */
    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setRestaurantTable($this);
        }

        return $this;
    }

    /** Retire le lien sans supprimer l’entité {@see Reservation} (orphanRemoval désactivé). */
    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);

        return $this;
    }
}
