<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Stripe\Stripe;
use Stripe\PaymentIntent;

#[AsController]
class GuestCheckoutController extends AbstractController
{
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ProductRepository $productRepo,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!isset($data['user']) || !isset($data['reservation'])) {
            return $this->json(['message' => 'Données incomplètes.'], 400);
        }

        $userData = $data['user'];
        $resData = $data['reservation'];

        // 1. GESTION DE L'UTILISATEUR
        $user = $userRepo->findOneBy(['email' => $userData['email']]);

        if (!$user) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setFirstname($userData['firstname']);
            $user->setLastname($userData['lastname']);
            $user->setMobile($userData['mobile'] ?? null);
            $user->setConsentDataRetention($userData['consentDataRetention'] ?? false);
            $user->setCreatedAt(new \DateTime());
            $user->setUpdatedAt(new \DateTime());
            $user->setIsActive(true);
            $user->setIsOwner(false);
            $user->setRoles(['ROLE_USER']);

            $randomPassword = bin2hex(random_bytes(10));
            $user->setPassword($passwordHasher->hashPassword($user, $randomPassword));

            $em->persist($user);
        }

        // 2. CRÉATION DE LA RÉSERVATION
        $product = $productRepo->find($resData['productId']);
        if (!$product) {
            return $this->json(['message' => 'Hébergement introuvable.'], 404);
        }

        $startDate = new \DateTime($resData['startDate']);
        $endDate = new \DateTime($resData['endDate']);

        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setStartDate($startDate);
        $reservation->setEndDate($endDate);
        $reservation->setNbAdult($resData['nbAdults']);
        $reservation->setNbChildren($resData['nbChildren']);
        $reservation->addProduct($product);

        // NOUVEAU : On met isPaid à false par défaut !
        $reservation->setIsPaid(false);

        $em->persist($reservation);
        $em->flush();

        // 3. LE CALCUL DU PRIX (Identique à ton pricing.js)
        $basePrice = $product->getPrices()->first()->getPrice(); // Supposons que c'est 2000 pour 20.00€
        $nights = max(1, $startDate->diff($endDate)->days);

        $rawAccommodation = 0;
        $currentDate = clone $startDate;

        for ($i = 0; $i < $nights; $i++) {
            $month = (int)$currentDate->format('n');
            $day = (int)$currentDate->format('j');

            // Logique de Haute Saison
            $isHighSeason = ($month === 7 || $month === 8 || ($month === 6 && $day >= 21));

            if ($isHighSeason) {
                $rawAccommodation += $basePrice * 1.15;
            } else {
                $rawAccommodation += $basePrice;
            }
            $currentDate->modify('+1 day');
        }

        // Remise Long Séjour
        $discountMultiplier = min(floor($nights / 7) * 0.05, 0.25);
        $finalAccommodation = $rawAccommodation - ($rawAccommodation * $discountMultiplier);

        // Taxes (Exemple de valeurs dures basées sur tes fixtures)
        $taxeAdulte = 60; // 0.60€
        $taxeEnfant = 35; // 0.35€
        $totalTaxes = ($taxeAdulte * $resData['nbAdults'] * $nights) + ($taxeEnfant * $resData['nbChildren'] * $nights);

        // Piscine (si cochée)
        $totalPool = 0;
        if ($resData['wantsPool']) {
            $piscineAdulte = 150; // 1.50€
            $piscineEnfant = 100; // 1.00€
            $poolDays = $resData['poolDays'];
            $totalPool = ($piscineAdulte * $resData['nbAdults'] * $poolDays) + ($piscineEnfant * $resData['nbChildren'] * $poolDays);
        }

        // 🛑 TOTAL EN CENTIMES POUR STRIPE (On arrondit pour éviter les bugs de décimales)
        $grandTotalInCents = (int) round($finalAccommodation + $totalTaxes + $totalPool);

        // 4. STRIPE PAYMENT INTENT
        Stripe::setApiKey(trim($_ENV['STRIPE_SECRET_KEY']));

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $grandTotalInCents,
                'currency' => 'eur',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'reservation_id' => $reservation->getId(),
                    'user_email' => $user->getEmail()
                ],
            ]);

            return $this->json([
                'id' => $reservation->getId(),
                'clientSecret' => $paymentIntent->client_secret,
            ], 201);

        } catch (\Exception $e) {
            return $this->json(['message' => $e->getMessage()], 500);
        }
    }
}
