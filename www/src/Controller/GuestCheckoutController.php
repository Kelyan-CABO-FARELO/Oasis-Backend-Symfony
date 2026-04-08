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

        // 1. Sécurité basique
        if (!isset($data['user']) || !isset($data['reservation'])) {
            return $this->json(['message' => 'Données incomplètes.'], 400);
        }

        $userData = $data['user'];
        $resData = $data['reservation'];

        // 2. Gestion de l'utilisateur (Compte Invité)
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

            // Génération d'un mot de passe fort et aléatoire pour le compte invité
            $randomPassword = bin2hex(random_bytes(10));
            $hashedPassword = $passwordHasher->hashPassword($user, $randomPassword);
            $user->setPassword($hashedPassword);

            $em->persist($user);
        } else {
            // Si l'utilisateur existe déjà, on met juste à jour son consentement RGPD par précaution
            $user->setConsentDataRetention($userData['consentDataRetention'] ?? $user->isConsentDataRetention());
        }

        // 3. Création de la Réservation
        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setStartDate(new \DateTime($resData['startDate']));
        $reservation->setEndDate(new \DateTime($resData['endDate']));
        $reservation->setNbAdult($resData['nbAdults']);
        $reservation->setNbChildren($resData['nbChildren']);

        // Liaison avec l'hébergement
        $product = $productRepo->find($resData['productId']);
        if (!$product) {
            return $this->json(['message' => 'Hébergement introuvable.'], 404);
        }
        $reservation->addProduct($product);

        $em->persist($reservation);

        // 4. On sauvegarde le tout en base de données !
        $em->flush();

        // 5. On renvoie un succès à React !
        return $this->json([
            'message' => 'Réservation créée avec succès',
            'id' => $reservation->getId(),
            'userEmail' => $user->getEmail()
        ], 201);
    }
}
