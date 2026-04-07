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
        // Initialisation de Faker
        $faker = Factory::create('fr_FR');

        // Récupération dynamique de l'année en cours
        $currentYear = (new \DateTime())->format('Y');

        // Catégories
        $categories = [
            ['type' => 'm-h', 'title' => 'M-H 6-8 personnes', 'basePrice' => 8500],
            ['type' => 'm-h', 'title' => 'M-H 5 pers', 'basePrice' => 7000],
            ['type' => 'm-h', 'title' => 'M-H 4 pers', 'basePrice' => 5500],
            ['type' => 'm-h', 'title' => 'M-H 3 pers', 'basePrice' => 4500],
            ['type' => 'caravane', 'title' => 'Caravane 6 places', 'basePrice' => 5000],
            ['type' => 'caravane', 'title' => 'Caravane 4 places', 'basePrice' => 4000],
            ['type' => 'caravane', 'title' => 'Caravane 2 places', 'basePrice' => 3000],
            ['type' => 'emplacement', 'title' => 'Emplacement 12 m²', 'basePrice' => 2500],
            ['type' => 'emplacement', 'title' => 'Emplacement 8 m²', 'basePrice' => 1500],
        ];

        // Génération de 90 hébergements
        for ($i = 1; $i <= 90; $i++) {
            $product = new Product();

            $category = $faker->randomElement($categories);

            $product->setTitle($category['title'] . ' n°' . $i);
            $product->setDescription($faker->paragraph(3));

            // 1. PRIX
            $price = new Price();
            $price->setPrice($category['basePrice']);
            $product->addPrice($price);
            $manager->persist($price);

            // 2. IMAGE
            $media = new Media();
            if ($category['type'] === 'm-h') {
                $media->setPath('placeholder_mh.jpg');
            } elseif ($category['type'] === 'caravane') {
                $media->setPath('placeholder_caravan.jpg');
            } else {
                $media->setPath('placeholder_tent.jpg');
            }
            $product->addMedium($media);
            $manager->persist($media);

            // 3. RÉSERVATIONS DYNAMIQUES
            $nbReservations = $faker->numberBetween(0, 3);

            for ($r = 0; $r < $nbReservations; $r++) {
                $reservation = new Reservation();

                $startDate = $faker->dateTimeBetween("$currentYear-05-05", "$currentYear-09-25");
                $endDate = (clone $startDate)->modify('+' . $faker->numberBetween(2, 15) . ' days');

                $reservation->setStartDate($startDate);
                $reservation->setEndDate($endDate);
                $reservation->addProduct($product);

                // 🛑 CORRECTION ICI : On ajoute des passagers aléatoires
                $reservation->setNbAdult($faker->numberBetween(1, 4));
                $reservation->setNbChildren($faker->numberBetween(0, 3));

                $manager->persist($reservation);
            }

            $manager->persist($product);
        }

        $manager->flush();
    }
}
