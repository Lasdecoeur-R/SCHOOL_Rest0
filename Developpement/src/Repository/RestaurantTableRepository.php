<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RestaurantTable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Tables de salle (capacité, numéro, actif).
 *
 * @extends ServiceEntityRepository<RestaurantTable>
 */
class RestaurantTableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantTable::class);
    }
}
