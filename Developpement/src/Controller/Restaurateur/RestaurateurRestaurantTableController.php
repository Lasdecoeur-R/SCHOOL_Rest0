<?php

declare(strict_types=1);

namespace App\Controller\Restaurateur;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use App\Entity\User;
use App\Form\RestaurantTableFormType;
use App\Repository\RestaurantTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des tables de salle pour l’établissement du restaurateur connecté (US-R03).
 */
#[Route('/restaurateur/tables')]
#[IsGranted('ROLE_RESTAURATEUR')]
final class RestaurateurRestaurantTableController extends AbstractController
{
    #[Route('', name: 'app_restaurateur_tables_index', methods: ['GET'])]
    public function index(RestaurantTableRepository $restaurantTableRepository, EntityManagerInterface $entityManager): Response
    {
        $restaurant = $this->currentUserRestaurant();
        if (null === $restaurant) {
            $this->addFlash('error', 'Aucun établissement n’est relié à ce compte. Exécutez les migrations puis rechargez vos fixtures, ou créez votre établissement via l’inscription.');

            return $this->redirectToRoute('app_restaurateur_dashboard');
        }

        $tables = $restaurantTableRepository->findByRestaurantOrderedByNumber($restaurant);

        $busyIds = array_flip(array_map(
            static fn (mixed $id): int => (int) $id,
            $entityManager->createQueryBuilder()
                ->select('IDENTITY(r.restaurantTable)')
                ->from(Reservation::class, 'r')
                ->distinct()
                ->getQuery()
                ->getSingleColumnResult(),
        ));

        $deletable = [];
        foreach ($tables as $t) {
            $deletable[$t->getId()] = !isset($busyIds[$t->getId()]);
        }

        return $this->render('restaurateur/tables/index.html.twig', [
            'tables' => $tables,
            'deletable' => $deletable,
            'establishmentLabel' => $restaurant->getName(),
        ]);
    }

    #[Route('/nouvelle', name: 'app_restaurateur_tables_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $restaurant = $this->currentUserRestaurant();
        if (null === $restaurant) {
            $this->addFlash('error', 'Aucun établissement n’est relié à ce compte.');

            return $this->redirectToRoute('app_restaurateur_dashboard');
        }

        $table = new RestaurantTable();
        $table->setRestaurant($restaurant);
        $form = $this->createForm(RestaurantTableFormType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($table);
            $entityManager->flush();
            $this->addFlash('success', 'Table enregistrée.');

            return $this->redirectToRoute('app_restaurateur_tables_index');
        }

        return $this->render('restaurateur/tables/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle table',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_restaurateur_tables_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, RestaurantTable $table, EntityManagerInterface $entityManager): Response
    {
        $this->assertTableOwnedByCurrentUserOrDeny($table);

        $form = $this->createForm(RestaurantTableFormType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Table mise à jour.');

            return $this->redirectToRoute('app_restaurateur_tables_index');
        }

        return $this->render('restaurateur/tables/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la table '.$table->getNumber(),
            'table' => $table,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_restaurateur_tables_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, RestaurantTable $table, EntityManagerInterface $entityManager): Response
    {
        $this->assertTableOwnedByCurrentUserOrDeny($table);

        if (!$this->isCsrfTokenValid('delete_table_'.$table->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $count = (int) $entityManager->getRepository(Reservation::class)->count(['restaurantTable' => $table]);
        if ($count > 0) {
            $this->addFlash('error', 'Impossible de supprimer : des réservations existent pour cette table. Décochez « En service » pour la retirer du planning.');

            return $this->redirectToRoute('app_restaurateur_tables_index');
        }

        $entityManager->remove($table);
        $entityManager->flush();
        $this->addFlash('success', 'Table supprimée.');

        return $this->redirectToRoute('app_restaurateur_tables_index');
    }

    private function currentUserRestaurant(): ?Restaurant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getRestaurant();
    }

    private function assertTableOwnedByCurrentUserOrDeny(RestaurantTable $table): void
    {
        $mine = $this->currentUserRestaurant();
        $owner = $table->getRestaurant();
        if (null === $mine || null === $owner || $mine->getId() !== $owner->getId()) {
            throw $this->createAccessDeniedException('Cette table n’appartient pas à votre établissement.');
        }
    }
}
