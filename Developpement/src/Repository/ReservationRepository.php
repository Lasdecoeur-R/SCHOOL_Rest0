<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Accès aux réservations client et requêtes dédiées au planning restaurateur.
 *
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Réservations confirmées pour un établissement et un jour donnés.
     *
     * @return list<Reservation>
     */
    public function findConfirmedByRestaurantAndDate(Restaurant $restaurant, \DateTimeImmutable $day): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.restaurantTable', 't')
            ->addSelect('t')
            ->where('t.restaurant = :restaurant')
            ->andWhere('r.reservationDate = :day')
            ->andWhere('r.status = :status')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('day', $day->setTime(0, 0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('status', Reservation::STATUS_CONFIRMED)
            ->orderBy('r.slotAt', 'ASC')
            ->addOrderBy('t.number', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
