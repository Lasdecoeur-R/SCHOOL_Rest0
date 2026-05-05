<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d’accueil publique. Le parcours « Réserver » : {@see \App\Controller\ReservationPublicController} (route app_reserver).
 *
 * Connexion : {@see SecurityController} (route app_login).
 */
final class HomeController extends AbstractController
{
    /** Page d’accueil publique du projet (présentation / liens). */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
