<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression d’un {@see Restaurant} : la BDD impose RESTRICT sur les réservations liées aux tables.
 */
final class RestaurantDeletionGuard
{
    public function countReservationsLinkedToRestaurant(Restaurant $restaurant, EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(res.id)')
            ->from(Reservation::class, 'res')
            ->join('res.restaurantTable', 'tbl')
            ->where('tbl.restaurant = :r')
            ->setParameter('r', $restaurant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTables(Restaurant $restaurant, EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(RestaurantTable::class, 't')
            ->where('t.restaurant = :r')
            ->setParameter('r', $restaurant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function canDelete(Restaurant $restaurant, EntityManagerInterface $entityManager): bool
    {
        return 0 === $this->countReservationsLinkedToRestaurant($restaurant, $entityManager);
    }
}
