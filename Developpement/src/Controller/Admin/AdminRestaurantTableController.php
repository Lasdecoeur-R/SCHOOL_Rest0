<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Entity\RestaurantTable;
use App\Form\RestaurantTableFormType;
use App\Repository\RestaurantTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des tables de salle (CAS_UTILISATION scénario 3 cas 1).
 */
#[Route('/admin/tables')]
#[IsGranted('ROLE_RESTAURATEUR')]
final class AdminRestaurantTableController extends AbstractController
{
    #[Route('', name: 'app_admin_tables_index', methods: ['GET'])]
    public function index(RestaurantTableRepository $restaurantTableRepository, EntityManagerInterface $entityManager): Response
    {
        $tables = $restaurantTableRepository->findAllOrderedByNumber();
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

        return $this->render('admin/tables/index.html.twig', [
            'tables' => $tables,
            'deletable' => $deletable,
        ]);
    }

    #[Route('/nouvelle', name: 'app_admin_tables_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $table = new RestaurantTable();
        $form = $this->createForm(RestaurantTableFormType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($table);
            $entityManager->flush();
            $this->addFlash('success', 'Table enregistrée.');

            return $this->redirectToRoute('app_admin_tables_index');
        }

        return $this->render('admin/tables/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle table',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_admin_tables_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, RestaurantTable $table, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RestaurantTableFormType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Table mise à jour.');

            return $this->redirectToRoute('app_admin_tables_index');
        }

        return $this->render('admin/tables/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la table '.$table->getNumber(),
            'table' => $table,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_tables_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, RestaurantTable $table, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_table_'.$table->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $count = (int) $entityManager->getRepository(Reservation::class)->count(['restaurantTable' => $table]);
        if ($count > 0) {
            $this->addFlash('error', 'Impossible de supprimer : des réservations existent pour cette table. Décochez « En service » pour la retirer du planning.');

            return $this->redirectToRoute('app_admin_tables_index');
        }

        $entityManager->remove($table);
        $entityManager->flush();
        $this->addFlash('success', 'Table supprimée.');

        return $this->redirectToRoute('app_admin_tables_index');
    }
}
