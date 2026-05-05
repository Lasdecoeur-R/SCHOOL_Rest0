<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Restaurant;
use App\Entity\User;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que l’inscription HTTP crée bien une ligne {@see Restaurant} + {@see User} liées.
 */
final class RegistrationControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite requise (voir .env.test).');
        }

        self::ensureKernelShutdown();
        self::bootKernel();

        $em = static::getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);

        self::ensureKernelShutdown();
    }

    public function testRegisterPersistsRestaurantLinkedToUser(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon compte')->form();
        $form['registration_form[email]'] = 'nouveau@test.resto';
        $form['registration_form[plainPassword][first]'] = 'motdepasse12';
        $form['registration_form[plainPassword][second]'] = 'motdepasse12';
        $form['registration_form[restaurantName]'] = 'Chez inscription test';

        $client->submit($form);

        self::assertResponseRedirects('/login');

        $em = static::getContainer()->get('doctrine')->getManager();

        $restaurant = $em->getRepository(Restaurant::class)->findOneBy(['name' => 'Chez inscription test']);
        self::assertInstanceOf(Restaurant::class, $restaurant);

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'nouveau@test.resto']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame($restaurant->getId(), $user->getRestaurant()?->getId());
    }
}
