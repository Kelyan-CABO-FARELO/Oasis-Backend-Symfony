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

        // Fonction pour créer un hébergement (sans toucher aux entités !)
        $createSpecificProduct = function($title, $basePrice, $imageName) use ($faker, $manager, $currentYear, &$globalIndex) {
            $product = new Product();

            $product->setTitle($title . ' n°' . $globalIndex);
            $product->setDescription($faker->paragraph(3));

            // Prix classique
            $price = new Price();
            $price->setPrice($basePrice);
            $product->addPrice($price);
            $manager->persist($price);

            // Image
            $media = new Media();
            $media->setPath($imageName);
            $product->addMedium($media);
            $manager->persist($media);

            // Réservations avec le nbAdult et nbChildren déjà prévus dans votre entité Reservation
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
        // 1. LES 50 MOBIL-HOMES (IDs 1 à 50)
        // ==========================================
        for ($i = 0; $i < 14; $i++) {
            $createSpecificProduct('M-H 3 pers', 4500, 'placeholder_mh.jpg');
        }
        for ($i = 0; $i < 13; $i++) {
            $createSpecificProduct('M-H 4 pers', 5500, 'placeholder_mh.jpg');
        }
        for ($i = 0; $i < 17; $i++) {
            $createSpecificProduct('M-H 5 pers', 7000, 'placeholder_mh.jpg');
        }
        for ($i = 0; $i < 6; $i++) {
            $createSpecificProduct('M-H 6-8 personnes', 8500, 'placeholder_mh.jpg');
        }

        // ==========================================
        // 2. LES 10 CARAVANES (IDs 51 à 60)
        // ==========================================
        for ($i = 0; $i < 5; $i++) {
            $createSpecificProduct('Caravane 6 places', 5000, 'placeholder_caravan.jpg');
        }
        for ($i = 0; $i < 2; $i++) {
            $createSpecificProduct('Caravane 4 places', 4000, 'placeholder_caravan.jpg');
        }
        for ($i = 0; $i < 3; $i++) {
            $createSpecificProduct('Caravane 2 places', 3000, 'placeholder_caravan.jpg');
        }

        // ==========================================
        // 3. LES 30 EMPLACEMENTS NUS (IDs 61 à 90)
        // ==========================================
        for ($i = 0; $i < 19; $i++) {
            $createSpecificProduct('Emplacement 8 m²', 1500, 'placeholder_tent.jpg');
        }
        for ($i = 0; $i < 11; $i++) {
            $createSpecificProduct('Emplacement 12 m²', 2500, 'placeholder_tent.jpg');
        }

        // ==========================================
        // 4. LES EXTRAS (En tant que Produits, à la fin)
        // ==========================================
        $createExtra = function($title, $priceValue, $desc) use ($manager) {
            $extra = new Product();
            $extra->setTitle($title);
            $extra->setDescription($desc);

            $price = new Price();
            $price->setPrice($priceValue);
            $extra->addPrice($price);

            $manager->persist($price);
            $manager->persist($extra);
        };

        // Création de vos tarifs annexes sans toucher aux entités !
        $createExtra('Taxe de séjour', 150, 'Taxe de séjour par nuitée et par adulte.');
        $createExtra('Accès piscine', 500, 'Accès illimité à l\'espace aquatique.');
        $createExtra('Tarif Adulte', 1500, 'Tarif par nuitée pour un adulte supplémentaire sur emplacement.');
        $createExtra('Tarif Enfant', 1000, 'Tarif par nuitée pour un enfant supplémentaire sur emplacement.');

        $manager->flush();
    }
}
