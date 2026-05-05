<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Redirection après connexion selon le rôle ({@see ROLE_ADMIN} plateforme vs {@see ROLE_RESTAURATEUR}).
 */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $roles = $token->getRoleNames();

        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        if (\in_array('ROLE_RESTAURATEUR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_restaurateur_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }
}
