<?php

declare(strict_types=1);

namespace App\Controller\Restaurateur;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Planning des réservations restaurateur : filtre par date + détail réservation.
 */
#[Route('/restaurateur/planning')]
#[IsGranted('ROLE_RESTAURATEUR')]
final class RestaurateurPlanningController extends AbstractController
{
    #[Route('', name: 'app_restaurateur_planning', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository): Response
    {
        $restaurant = $this->currentUserRestaurant();
        if (null === $restaurant) {
            $this->addFlash('error', 'Aucun établissement n’est relié à ce compte.');

            return $this->redirectToRoute('app_restaurateur_dashboard');
        }

        $tz = new \DateTimeZone('Europe/Paris');
        $today = (new \DateTimeImmutable('now', $tz))->setTime(0, 0, 0);
        $selectedDate = $this->resolvePlanningDate($request, $tz, $today);

        $reservations = $reservationRepository->findConfirmedByRestaurantAndDate($restaurant, $selectedDate);

        return $this->render('restaurateur/planning/index.html.twig', [
            'restaurant' => $restaurant,
            'selectedDate' => $selectedDate,
            'minDate' => $today->format('Y-m-d'),
            'reservations' => $reservations,
        ]);
    }

    #[Route('/reservation/{id}', name: 'app_restaurateur_planning_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(Reservation $reservation): Response
    {
        $restaurant = $this->currentUserRestaurant();
        $owner = $reservation->getRestaurantTable()?->getRestaurant();
        if (null === $restaurant || null === $owner || $owner->getId() !== $restaurant->getId()) {
            throw $this->createAccessDeniedException('Cette réservation n’appartient pas à votre établissement.');
        }

        return $this->render('restaurateur/planning/detail.html.twig', [
            'reservation' => $reservation,
            'restaurant' => $restaurant,
        ]);
    }

    private function currentUserRestaurant(): ?Restaurant
    {
        $u = $this->getUser();
        if (!$u instanceof User) {
            return null;
        }

        return $u->getRestaurant();
    }

    private function resolvePlanningDate(Request $request, \DateTimeZone $tz, \DateTimeImmutable $fallback): \DateTimeImmutable
    {
        $raw = $request->query->get('date');
        if (!\is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $fallback;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
        if (false === $parsed) {
            return $fallback;
        }

        return $parsed->setTime(0, 0, 0);
    }
}
