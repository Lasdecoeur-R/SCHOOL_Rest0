<?php

declare(strict_types=1);

namespace App\Controller\Restaurateur;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Accueil de l’espace restaurateur : tables, établissement et planning.
 */
#[Route('/restaurateur')]
#[IsGranted('ROLE_RESTAURATEUR')]
final class RestaurateurDashboardController extends AbstractController
{
    #[Route('', name: 'app_restaurateur_dashboard')]
    public function index(): Response
    {
        return $this->render('restaurateur/dashboard.html.twig');
    }
}
