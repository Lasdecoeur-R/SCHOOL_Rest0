<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages publiques minimales (accueil projet, lien vers résa).
 *
 * Connexion : \App\Controller\SecurityController (route app_login).
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/reserver', name: 'app_reserver')]
    public function reserver(): Response
    {
        return $this->render('home/reserver.html.twig');
    }
}
