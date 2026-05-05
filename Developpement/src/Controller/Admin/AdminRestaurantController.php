<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Restaurant;
use App\Form\RestaurantFormType;
use App\Repository\RestaurantRepository;
use App\Service\RestaurantDeletionGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des fiches {@see Restaurant} réservé à l’administrateur plateforme.
 */
#[Route('/admin/restaurants')]
#[IsGranted('ROLE_ADMIN')]
final class AdminRestaurantController extends AbstractController
{
    #[Route('', name: 'app_admin_restaurants_index', methods: ['GET'])]
    public function index(RestaurantRepository $restaurantRepository, RestaurantDeletionGuard $deletionGuard, EntityManagerInterface $entityManager): Response
    {
        $rows = [];
        foreach ($restaurantRepository->findAllOrderedByName() as $r) {
            $rows[] = [
                'restaurant' => $r,
                'reservationCount' => $deletionGuard->countReservationsLinkedToRestaurant($r, $entityManager),
                'canDelete' => $deletionGuard->canDelete($r, $entityManager),
            ];
        }

        return $this->render('admin/restaurants/index.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/nouveau', name: 'app_admin_restaurants_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $restaurant = new Restaurant();
        $form = $this->createForm(RestaurantFormType::class, $restaurant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($restaurant);
            $entityManager->flush();
            $this->addFlash('success', 'Établissement créé (reste invisible sur /reserver tant qu’aucun compte restaurateur n’y est relié).');

            return $this->redirectToRoute('app_admin_restaurants_index');
        }

        return $this->render('admin/restaurants/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel établissement',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_restaurants_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Restaurant $restaurant, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RestaurantFormType::class, $restaurant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Établissement mis à jour.');

            return $this->redirectToRoute('app_admin_restaurants_index');
        }

        return $this->render('admin/restaurants/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier « '.$restaurant->getName().' »',
            'restaurant' => $restaurant,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_restaurants_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        Restaurant $restaurant,
        RestaurantDeletionGuard $deletionGuard,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_restaurant_'.$restaurant->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$deletionGuard->canDelete($restaurant, $entityManager)) {
            $this->addFlash('error', 'Suppression impossible : des réservations existent encore pour des tables de cet établissement.');

            return $this->redirectToRoute('app_admin_restaurants_index');
        }

        $entityManager->remove($restaurant);
        $entityManager->flush();
        $this->addFlash('success', 'Établissement supprimé (tables supprimées en cascade ; comptes restaurateurs reliés ont perdu ce lien).');

        return $this->redirectToRoute('app_admin_restaurants_index');
    }
}
