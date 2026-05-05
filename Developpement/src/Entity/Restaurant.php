<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RestaurantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Établissement proposé sur le site ; les tables ({@see RestaurantTable}) et le planning sont rattachés à un restaurant.
 */
#[ORM\Entity(repositoryClass: RestaurantRepository::class)]
#[ORM\Table(name: 'restaurant')]
class Restaurant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Le nom du restaurant est obligatoire.')]
    #[Assert\Length(max: 180)]
    private ?string $name = null;

    /** @var Collection<int, RestaurantTable> */
    #[ORM\OneToMany(targetEntity: RestaurantTable::class, mappedBy: 'restaurant')]
    private Collection $tables;

    /** Comptes restaurateur rattachés (un établissement par compte en version actuelle). */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'restaurant')]
    private Collection $staffUsers;

    public function __construct()
    {
        $this->tables = new ArrayCollection();
        $this->staffUsers = new ArrayCollection();
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

    /**
     * @return Collection<int, RestaurantTable>
     */
    public function getTables(): Collection
    {
        return $this->tables;
    }

    /**
     * Comptes reliés à cet établissement (inverse de {@see User::$restaurant}).
     *
     * @return Collection<int, User>
     */
    public function getStaffUsers(): Collection
    {
        return $this->staffUsers;
    }

    /** Libellé affichage liste / liste déroulante. */
    public function __toString(): string
    {
        return (string) ($this->name ?? '');
    }
}
