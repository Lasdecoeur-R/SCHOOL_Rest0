<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Tables de salle : CRUD admin et requêtes pour le service de réservation.
 *
 * @extends ServiceEntityRepository<RestaurantTable>
 */
class RestaurantTableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantTable::class);
    }

    /** @return list<RestaurantTable> Toutes les tables (usage global rare). */
    public function findAllOrderedByNumber(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.number', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste d’un établissement donné pour l’admin.
     *
     * @return list<RestaurantTable>
     */
    public function findByRestaurantOrderedByNumber(Restaurant $restaurant): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.restaurant = :r')
            ->setParameter('r', $restaurant)
            ->orderBy('t.number', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
