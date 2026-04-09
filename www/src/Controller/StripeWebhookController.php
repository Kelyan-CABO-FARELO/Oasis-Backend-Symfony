<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(Request $request, ReservationRepository $reservationRepo, EntityManagerInterface $em): Response
    {
        // Récupération de la clé secrète du webhook (générée par Stripe CLI)
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            // Vérification de l'authenticité de la requête
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Payload invalide', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Signature invalide', 400);
        }

        // On réagit uniquement quand le paiement est un succès total
        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $reservationId = $paymentIntent->metadata->reservation_id ?? null;

            if ($reservationId) {
                $reservation = $reservationRepo->find($reservationId);

                if ($reservation) {
                    // ✅ On valide enfin la réservation en base de données
                    $reservation->setIsPaid(true);
                    $em->flush();
                }
            }
        }

        return new Response('Webhook traité', 200);
    }
}
