<?php

namespace App\Controller;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ManageBookingController extends AbstractController
{
    // 👇 CORRECTION : Ajout de /api/ dans la route
    #[Route('/api/manage-booking/{id}', name: 'api_manage_booking', methods: ['GET'])]
    public function getBooking(Reservation $reservation, Request $request): JsonResponse
    {
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
            'isPaid' => $reservation->getIsPaid(),
            'products' => $productTitles,
            'user' => [
                'firstname' => $reservation->getUser()->getFirstname(),
                'lastname' => $reservation->getUser()->getLastname(),
            ],
            'poolDays' => $reservation->getPoolDays()
        ]);
    }

    // 👇 CORRECTION : Ajout de /api/ dans la route
    #[Route('/api/manage-booking/{id}/cancel', name: 'api_manage_booking_cancel', methods: ['DELETE'])]
    public function cancelBooking(Reservation $reservation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $token = $request->query->get('token');
        if (!$token || $reservation->getManagementToken() !== $token) {
            return $this->json(['message' => 'Accès refusé ou lien expiré.'], 403);
        }

        $em->remove($reservation);
        $em->flush();

        return $this->json(['message' => 'Réservation annulée avec succès.'], 200);
    }

    // 👇 CORRECTION : Ajout de /api/ dans la route
    #[Route('/api/manage-booking/{id}/add-pool', name: 'api_manage_booking_add_pool', methods: ['POST'])]
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
            $reservation->setPoolDays($poolDays);
            $em->flush();
            return $this->json(['message' => "Option Espace Aquatique ajoutée pour $poolDays jour(s)."], 200);
        }

        return $this->json(['message' => 'Impossible de trouver l\'option.'], 404);
    }
}
