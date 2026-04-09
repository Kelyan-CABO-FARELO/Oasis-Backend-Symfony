<?php

namespace App\Controller;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface; // 👈 NOUVEL IMPORT POUR SUPPRIMER
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ManageBookingController extends AbstractController
{
    // ... Ta fonction getBooking() actuelle est ici ...
    #[Route('/manage-booking/{id}', name: 'api_manage_booking', methods: ['GET'])]
    public function getBooking(Reservation $reservation, Request $request): JsonResponse
    {
        // ... (Ne change rien à cette fonction) ...
        $token = $request->query->get('token');

        if (!$token || $reservation->getManagementToken() !== $token) {
            return $this->json(['message' => 'Accès refusé ou lien expiré.'], 403);
        }

        $productTitles = [];
        foreach ($reservation->getProducts() as $product) {
            $productTitles[] = $product->getTitle();
        }

        return $this->json([
            'id' => $reservation->getId(),
            'startDate' => $reservation->getStartDate()->format('d/m/Y'),
            'endDate' => $reservation->getEndDate()->format('d/m/Y'),
            'nbAdult' => $reservation->getNbAdult(),
            'nbChildren' => $reservation->getNbChildren(),
            'isPaid' => $reservation->isPaid(),
            'products' => $productTitles,
            'user' => [
                'firstname' => $reservation->getUser()->getFirstname(),
                'lastname' => $reservation->getUser()->getLastname(),
            ]
        ]);
    }

    // 👇 NOUVELLE FONCTION D'ANNULATION
    #[Route('/manage-booking/{id}/cancel', name: 'api_manage_booking_cancel', methods: ['DELETE'])]
    public function cancelBooking(Reservation $reservation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // 1. On revérifie la sécurité (Le jeton est-il bon ?)
        $token = $request->query->get('token');
        if (!$token || $reservation->getManagementToken() !== $token) {
            return $this->json(['message' => 'Accès refusé ou lien expiré.'], 403);
        }

        // 2. Si c'est bon, on supprime la réservation de la base de données
        $em->remove($reservation);
        $em->flush();

        // 3. On renvoie un message de succès
        return $this->json(['message' => 'Réservation annulée avec succès.'], 200);
    }

    // 👇 FONCTION D'AJOUT D'OPTIONS (PISCINE) MISE À JOUR
    #[Route('/manage-booking/{id}/add-pool', name: 'api_manage_booking_add_pool', methods: ['POST'])]
    public function addPoolOption(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        \App\Repository\ProductRepository $productRepo
    ): JsonResponse {
        $token = $request->query->get('token');
        if (!$token || $reservation->getManagementToken() !== $token) {
            return $this->json(['message' => 'Accès refusé ou lien expiré.'], 403);
        }

        // On récupère le nombre de jours envoyé par React
        $data = json_decode($request->getContent(), true);
        $poolDays = $data['poolDays'] ?? 1;

        $poolAdult = $productRepo->findOneBy(['title' => 'Accès piscine Adulte']);
        $poolChild = $productRepo->findOneBy(['title' => 'Accès piscine Enfant']);
        $added = false;

        if ($poolAdult && $reservation->getNbAdult() > 0) {
            $reservation->addProduct($poolAdult);
            $added = true;
        }
        if ($poolChild && $reservation->getNbChildren() > 0) {
            $reservation->addProduct($poolChild);
            $added = true;
        }

        if ($added) {
            $em->flush();
            // On renvoie un message personnalisé avec le nombre de jours !
            return $this->json(['message' => "Option Espace Aquatique ajoutée pour $poolDays jour(s)."], 200);
        }

        return $this->json(['message' => 'Impossible de trouver l\'option.'], 404);
    }
}
