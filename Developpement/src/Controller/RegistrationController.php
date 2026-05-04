<?php

declare(strict_types=1);

namespace App\Controller;

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
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            try {
                if ($form->isValid()) {
                    $user = new User();
                    $user->setEmail((string) $form->get('email')->getData());
                    $user->setRestaurantName($form->get('restaurantName')->getData() ?: null);
                    $plainPassword = (string) $form->get('plainPassword')->getData();
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                    $user->setRoles(['ROLE_RESTAURATEUR']);

                    $violations = $validator->validate($user);
                    if (\count($violations) > 0) {
                        foreach ($violations as $violation) {
                            $this->addFlash('error', $violation->getMessage());
                        }
                    } else {
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

    private function messageIndicatesMysqlUnreachable(string $message): bool
    {
        return str_contains($message, '[2002]')
            || str_contains($message, 'refusée')
            || str_contains($message, 'Connection refused');
    }
}
