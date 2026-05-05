<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Établissements : liste pour le formulaire public (repère par compte inscrit, voir méthodes).
 *
 * @extends ServiceEntityRepository<Restaurant>
 */
class RestaurantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Restaurant::class);
    }

    /**
     * Liste réservée au rôle plateforme ROLE_ADMIN : tous les enregistrements.
     *
     * @return list<Restaurant>
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.name', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Établissements affichés sur /reserver : au moins un compte créé (relation {@see User::$restaurant}.
     *
     * @return list<Restaurant> Tri stable par nom.
     */
    public function findRegisteredEstablishmentsOrderedByName(): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin(User::class, 'u', Join::WITH, 'u.restaurant = r')
            ->distinct()
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasActiveTable(Restaurant $restaurant): bool
    {
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(RestaurantTable::class, 't')
            ->where('t.restaurant = :r')
            ->andWhere('t.active = true')
            ->setParameter('r', $restaurant)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }
}
