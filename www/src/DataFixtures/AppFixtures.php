<?php

namespace App\DataFixtures;

use App\Entity\Media;
use App\Entity\Price;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Les Utilisateurs
        $admin = $this->loadAdmin($manager);
        $owners = $this->loadOwners($manager);

        // 2. Le Catalogue
        $this->loadMobileHomes($manager, $owners);
        $this->loadCaravanes($manager);
        $this->loadEmplacements($manager);
        $this->loadServicesPiscine($manager);
        $this->loadTaxesSejour($manager);

        $manager->flush();
    }

    private function loadAdmin(ObjectManager $manager): User
    {
        $admin = new User();
        $admin->setEmail('admin@admin.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $admin->setFirstname('Patrick');
        $admin->setLastname('Durand');
        $admin->setCreatedAt(new \DateTime());
        $admin->setUpdatedAt(new \DateTime()); // Ajouté pour corriger l'erreur
        $admin->setIsActive(true);
        $admin->setIsOwner(false);
        $manager->persist($admin);
        return $admin;
    }

    private function loadOwners(ObjectManager $manager): array
    {
        $owners = [];
        for ($i = 1; $i <= 15; $i++) {
            $user = new User();
            $user->setEmail("proprietaire$i@test.com");
            $user->setRoles(['ROLE_USER', 'ROLE_OWNER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'owner'));
            $user->setFirstname("Proprio_$i");
            $user->setLastname("Nom_$i");
            $user->setCreatedAt(new \DateTime());
            $user->setUpdatedAt(new \DateTime()); // Ajouté pour corriger l'erreur
            $user->setIsActive(true);
            $user->setIsOwner(true);
            $manager->persist($user);
            $owners[] = $user;
        }
        return $owners;
    }

    private function loadMobileHomes(ObjectManager $manager, array $owners): void
    {
        $types = [
            ['title' => 'M-H 3 personnes', 'price' => 2000, 'img' => 'mh-3.png'],
            ['title' => 'M-H 4 personnes', 'price' => 2400, 'img' => 'mh-4.png'],
            ['title' => 'M-H 5 personnes', 'price' => 2700, 'img' => 'mh-5.png'],
            ['title' => 'M-H 6-8 personnes', 'price' => 3400, 'img' => 'mh-68.png'],
        ];

        for ($i = 1; $i <= 50; $i++) {
            $config = $types[array_rand($types)];
            $mh = new Product();
            $mh->setTitle($config['title'] . " n°" . $i);
            $mh->setDescription("Mobile-home tout confort.");

            if ($i <= 30) {
                $mh->addUser($owners[($i - 1) % count($owners)]);
            }

            $this->addPriceToProduct($manager, $mh, $config['price']);
            $this->addMediaToProduct($manager, $mh, $config['img']);
            $manager->persist($mh);
        }
    }

    private function loadCaravanes(ObjectManager $manager): void
    {
        $types = [
            ['title' => 'Caravane 2 places', 'price' => 1500, 'img' => 'c-2.png'],
            ['title' => 'Caravane 4 places', 'price' => 1800, 'img' => 'c-4.png'],
            ['title' => 'Caravane 6 places', 'price' => 2400, 'img' => 'c-6.png'],
        ];

        for ($i = 1; $i <= 10; $i++) {
            $config = $types[array_rand($types)];
            $car = new Product();
            $car->setTitle($config['title'] . " n°" . $i);

            $this->addPriceToProduct($manager, $car, $config['price']);
            $this->addMediaToProduct($manager, $car, $config['img']);
            $manager->persist($car);
        }
    }

    private function loadEmplacements(ObjectManager $manager): void
    {
        $types = [
            ['title' => 'Emplacement 8 m²', 'price' => 1200, 'img' => 'e-8.png'],
            ['title' => 'Emplacement 12 m²', 'price' => 1400, 'img' => 'e-12.png'],
        ];

        for ($i = 1; $i <= 30; $i++) {
            $config = $types[array_rand($types)];
            $emp = new Product();
            $emp->setTitle($config['title'] . " n°" . $i);

            $this->addPriceToProduct($manager, $emp, $config['price']);
            $this->addMediaToProduct($manager, $emp, $config['img']);
            $manager->persist($emp);
        }
    }

    private function loadServicesPiscine(ObjectManager $manager): void
    {
        $services = [
            ['title' => 'Accès Piscine Enfant - 1 jour', 'price' => 100, 'duration' => 1, 'img' => 'pool-kids.png'],
            ['title' => 'Accès Piscine Adulte - 1 jour', 'price' => 150, 'duration' => 1, 'img' => 'pool-adults.png'],
            ['title' => 'Accès Piscine Enfant - 5 jours', 'price' => 500, 'duration' => 5, 'img' => 'pool-kids.png'],
            ['title' => 'Accès Piscine Adulte - 5 jours', 'price' => 750, 'duration' => 5, 'img' => 'pool-adults.png'],
            ['title' => 'Accès Piscine Enfant - 10 jours', 'price' => 1000, 'duration' => 10, 'img' => 'pool-kids.png'],
            ['title' => 'Accès Piscine Adulte - 10 jours', 'price' => 1500, 'duration' => 10, 'img' => 'pool-adults.png'],
        ];

        foreach ($services as $s) {
            $p = new Product();
            $p->setTitle($s['title']);
            $p->setDuration($s['duration']);
            $this->addPriceToProduct($manager, $p, $s['price']);
            $this->addMediaToProduct($manager, $p, $s['img']);
            $manager->persist($p);
        }
    }

    private function loadTaxesSejour(ObjectManager $manager): void
    {
        $taxes = [
            ['title' => 'Taxe de séjour Enfant', 'price' => 35],
            ['title' => 'Taxe de séjour Adulte', 'price' => 60],
        ];

        foreach ($taxes as $t) {
            $p = new Product();
            $p->setTitle($t['title']);
            $this->addPriceToProduct($manager, $p, $t['price']);
            $manager->persist($p);
        }
    }

    private function addPriceToProduct(ObjectManager $manager, Product $product, int $amount): void
    {
        $price = new Price();
        $price->setPrice($amount);
        $price->setProduct($product);
        $manager->persist($price);
    }

    private function addMediaToProduct(ObjectManager $manager, Product $product, string $filename): void
    {
        $media = new Media();
        $media->setPath($filename);
        $manager->persist($media);
        $product->addMedium($media);
    }
}
