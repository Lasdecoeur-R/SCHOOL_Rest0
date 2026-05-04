<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Zone back-office sous /admin (firewall + rôle restaurateur).
 */
#[Route('/admin')]
#[IsGranted('ROLE_RESTAURATEUR')]
final class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }
}
