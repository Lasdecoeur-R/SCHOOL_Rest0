<?php

declare(strict_types=1);

namespace App\Repository;

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

    /**
     * Liste complète triée par numéro affiché (ordre lexicographique sur la chaîne).
     *
     * @return list<RestaurantTable>
     */
    public function findAllOrderedByNumber(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.number', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
