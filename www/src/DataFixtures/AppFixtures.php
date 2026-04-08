<?php

namespace App\DataFixtures;

use App\Entity\Media;
use App\Entity\Price;
use App\Entity\Product;
use App\Entity\Reservation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $currentYear = (new \DateTime())->format('Y');

        $globalIndex = 1;

        // 👇 Plus besoin de $adultPrice et $childPrice, l'hébergement a un prix unique par nuit !
        $createSpecificProduct = function($title, $basePrice, $imageName) use ($faker, $manager, $currentYear, &$globalIndex) {
            $product = new Product();
            $product->setTitle($title . ' n°' . $globalIndex);
            $product->setDescription($faker->paragraph(3));

            // Prix de base de l'hébergement
            $price = new Price();
            $price->setPrice($basePrice);
            $product->addPrice($price);
            $manager->persist($price);

            // Image
            $media = new Media();
            $media->setPath($imageName);
            $product->addMedium($media);
            $manager->persist($media);

            // Réservations
            $nbReservations = $faker->numberBetween(0, 3);
            for ($r = 0; $r < $nbReservations; $r++) {
                $reservation = new Reservation();
                $startDate = $faker->dateTimeBetween("$currentYear-05-05", "$currentYear-09-25");
                $endDate = (clone $startDate)->modify('+' . $faker->numberBetween(2, 15) . ' days');

                $reservation->setStartDate($startDate);
                $reservation->setEndDate($endDate);
                $reservation->addProduct($product);
                $reservation->setNbAdult($faker->numberBetween(1, 4));
                $reservation->setNbChildren($faker->numberBetween(0, 3));

                $manager->persist($reservation);
            }

            $manager->persist($product);
            $globalIndex++;
        };

        // ==========================================
        // MOBIL-HOMES
        // ==========================================
        for ($i = 0; $i < 14; $i++) {
            $createSpecificProduct('MobileHome 3 personnes', 4500, 'mh-3.png');
        }
        for ($i = 0; $i < 13; $i++) {
            $createSpecificProduct('MobileHome 4 personnes', 5500, 'mh-4.png');
        }
        for ($i = 0; $i < 17; $i++) {
            $createSpecificProduct('MobileHome 5 personnes', 7000, 'mh-5.png');
        }
        for ($i = 0; $i < 6; $i++) {
            $createSpecificProduct('MobileHome 6-8 personnes', 8500, 'mh-68.png');
        }

        // ==========================================
        // CARAVANES
        // ==========================================
        for ($i = 0; $i < 5; $i++) {
            $createSpecificProduct('Caravane 6 places', 5000, 'c-6.png');
        }
        for ($i = 0; $i < 2; $i++) {
            $createSpecificProduct('Caravane 4 places', 4000, 'c-4.png');
        }
        for ($i = 0; $i < 3; $i++) {
            $createSpecificProduct('Caravane 2 places', 3000, 'c-2.png');
        }

        // ==========================================
        // EMPLACEMENTS NUS
        // ==========================================
        for ($i = 0; $i < 19; $i++) {
            $createSpecificProduct('Emplacement 8 m²', 1500, 'e-8.png');
        }
        for ($i = 0; $i < 11; $i++) {
            $createSpecificProduct('Emplacement 12 m²', 2500, 'e-12.png');
        }

        // ==========================================
        // EXTRAS (Taxe, Piscine...) - Traités comme des produits indépendants
        // ==========================================
        $createExtra = function($title, $priceValue, $desc, $imageName = null, $duration = null) use ($manager) {
            $extra = new Product();
            $extra->setTitle($title);
            $extra->setDescription($desc);

            // On définit la durée (ex: 1 jour)
            if ($duration !== null) {
                $extra->setDuration($duration);
            }

            $price = new Price();
            $price->setPrice($priceValue); // Le prix de l'extra
            $extra->addPrice($price);
            $manager->persist($price);

            if ($imageName !== null) {
                $media = new Media();
                $media->setPath($imageName);
                $extra->addMedium($media);
                $manager->persist($media);
            }

            $manager->persist($extra);
        };

        // Ajout des extras (Le dernier paramètre "1" correspond à duration = 1)
        $createExtra('Taxe de séjour Adulte', 150, 'Taxe de séjour par nuitée et par adulte.', null, 1);
        $createExtra('Taxe de séjour Enfant', 150, 'Taxe de séjour par nuitée et par enfant.', null, 1);
        $createExtra('Accès piscine Adulte', 500, 'Accès d\'un jour à l\'espace aquatique.', 'pool-adults.png', 1);
        $createExtra('Accès piscine Enfant', 500, 'Accès d\'un jour à l\'espace aquatique.', 'pool-kids.png', 1);

        $manager->flush();
    }
}
