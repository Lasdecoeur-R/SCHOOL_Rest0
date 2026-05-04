<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\ConnectionException;
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

        if ($form->isSubmitted() && $form->isValid()) {
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
                try {
                    $entityManager->flush();
                } catch (ConnectionException) {
                    $entityManager->clear();
                    $this->addFlash(
                        'error',
                        'Connexion à MySQL impossible (serveur arrêté ou DATABASE_URL incorrect). '
                        .'Démarrez MySQL (WAMP / Laragon / XAMPP / Docker), créez la base si besoin, '
                        .'puis vérifiez le fichier .env (ex. mysql://root:@127.0.0.1:3306/app?serverVersion=8.0.32&charset=utf8mb4).',
                    );

                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form,
                    ]);
                }

                $this->addFlash('success', 'Compte créé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
