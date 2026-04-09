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
}
