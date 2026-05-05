<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Inscription d'un compte restaurateur (ROLE_RESTAURATEUR).
 */
final class RegistrationController extends AbstractController
{
    /**
     * Crée un {@see User} restaurateur (hash du mot de passe, rôle ROLE_RESTAURATEUR).
     * Gère gracieusement l’indisponibilité MySQL (message orienté développeur / démo locale).
     */
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_restaurateur_dashboard');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            try {
                // isValid() peut lever (UniqueEntity) : tout reste dans le try pour un seul message flash d’erreur réseau.
                if ($form->isValid()) {
                    $user = new User();
                    $user->setEmail((string) $form->get('email')->getData());
                    $establishmentLabel = trim((string) ($form->get('restaurantName')->getData() ?? ''));
                    $displayName = '' !== $establishmentLabel ? $establishmentLabel : sprintf('Établissement (%s)', $user->getEmail());

                    $restaurant = new Restaurant();
                    $restaurant->setName($displayName);

                    $user->setRestaurantName($establishmentLabel !== '' ? $establishmentLabel : null);
                    $user->setRestaurant($restaurant);
                    $plainPassword = (string) $form->get('plainPassword')->getData();
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                    $user->setRoles(['ROLE_RESTAURATEUR']);

                    // Double passe validateur entités (Restaurant + utilisateur) avant toute persistance.
                    $violationsMessages = [];
                    foreach ($validator->validate($restaurant) as $v) {
                        $violationsMessages[] = $v->getMessage();
                    }
                    foreach ($validator->validate($user) as $v) {
                        $violationsMessages[] = $v->getMessage();
                    }

                    if (\count($violationsMessages) > 0) {
                        foreach ($violationsMessages as $message) {
                            $this->addFlash('error', $message);
                        }
                    } else {
                        /** @see User::$restaurant cascade persist pour garantir INSERT restaurant puis user.restaurant_id. */
                        $entityManager->persist($user);
                        $entityManager->flush();

                        $this->addFlash('success', 'Compte créé. Vous pouvez vous connecter.');

                        return $this->redirectToRoute('app_login');
                    }
                }
            } catch (\Throwable $e) {
                if (!$this->isLikelyMysqlUnreachable($e)) {
                    throw $e;
                }

                $this->addFlash(
                    'error',
                    'Connexion à MySQL impossible (serveur arrêté ou DATABASE_URL incorrect). '
                    .'Démarrez MySQL (WAMP / Laragon) ou, avec Docker : dans Developpement/, exécutez « docker compose up -d ». '
                    .'Vérifiez .env / .env.dev (voir compose.yaml pour l’URL avec Docker).',
                );

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Détecte une erreur « connexion refusée » MySQL (2002), y compris si Doctrine expose un
     * {@see DriverException} plutôt qu’un {@see ConnectionException} (conversion PDO / code).
     */
    private function isLikelyMysqlUnreachable(\Throwable $e): bool
    {
        $c = $e;
        while (true) {
            if ($c instanceof ConnectionException) {
                return true;
            }
            if ($c instanceof DriverException && $this->messageIndicatesMysqlUnreachable($c->getMessage())) {
                return true;
            }
            if ($c instanceof \PDOException && (2002 === (int) $c->getCode() || $this->messageIndicatesMysqlUnreachable($c->getMessage()))) {
                return true;
            }
            if ($this->messageIndicatesMysqlUnreachable($c->getMessage())) {
                return true;
            }
            $prev = $c->getPrevious();
            if (!$prev instanceof \Throwable) {
                break;
            }
            $c = $prev;
        }

        return false;
    }

    /** Repère les messages PDO / MySQL habituels quand le serveur SQL n’écoute pas. */
    private function messageIndicatesMysqlUnreachable(string $message): bool
    {
        return str_contains($message, '[2002]')
            || str_contains($message, 'refusée')
            || str_contains($message, 'Connection refused');
    }
}
