<?php

declare(strict_types=1);

namespace App\Controller\Restaurateur;

use App\Entity\User;
use App\Service\RestaurantDeletionGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de l’établissement du restaurateur (suppression définitive sous conditions).
 */
#[Route('/restaurateur/mon-etablissement')]
#[IsGranted('ROLE_RESTAURATEUR')]
final class RestaurateurEstablishmentController extends AbstractController
{
    #[Route('', name: 'app_restaurateur_establishment', methods: ['GET'])]
    public function show(RestaurantDeletionGuard $deletionGuard, EntityManagerInterface $entityManager): Response
    {
        $establishmentProblem = $this->redirectUnlessUserHasEstablishment();
        if (null !== $establishmentProblem) {
            return $establishmentProblem;
        }

        $user = $this->currentUserStrict();
        $restaurant = $user->getRestaurant();
        assert(null !== $restaurant);

        return $this->render('restaurateur/establishment/show.html.twig', [
            'restaurant' => $restaurant,
            'reservationCount' => $deletionGuard->countReservationsLinkedToRestaurant($restaurant, $entityManager),
            'tableCount' => $deletionGuard->countTables($restaurant, $entityManager),
            'canDelete' => $deletionGuard->canDelete($restaurant, $entityManager),
        ]);
    }

    #[Route('/supprimer', name: 'app_restaurateur_establishment_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        RestaurantDeletionGuard $deletionGuard,
        EntityManagerInterface $entityManager,
    ): Response {
        $establishmentProblem = $this->redirectUnlessUserHasEstablishment();
        if (null !== $establishmentProblem) {
            return $establishmentProblem;
        }

        $user = $this->currentUserStrict();
        $restaurant = $user->getRestaurant();
        assert(null !== $restaurant);

        if (!$this->isCsrfTokenValid('delete_restaurant_establishment', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$deletionGuard->canDelete($restaurant, $entityManager)) {
            $this->addFlash('error', 'Impossible de supprimer l’établissement tant qu’une réservation existe (même passée). Traitez ou annulez d’abord les réservations concernées.');

            return $this->redirectToRoute('app_restaurateur_establishment');
        }

        $entityManager->remove($restaurant);
        $entityManager->flush();

        $this->addFlash('success', 'Établissement supprimé. Votre compte reste actif ; un administrateur peut rattacher votre compte à une nouvelle fiche restaurant.');

        return $this->redirectToRoute('app_restaurateur_dashboard');
    }

    /** @return Response|null Redirection si utilisateur invalide ou sans établissement. */
    private function redirectUnlessUserHasEstablishment(): ?Response
    {
        $u = $this->getUser();
        if (!$u instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (null === $u->getRestaurant()) {
            $this->addFlash('error', 'Aucun établissement n’est relié à ce compte.');

            return $this->redirectToRoute('app_restaurateur_dashboard');
        }

        return null;
    }

    private function currentUserStrict(): User
    {
        $u = $this->getUser();
        if (!$u instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $u;
    }
}
