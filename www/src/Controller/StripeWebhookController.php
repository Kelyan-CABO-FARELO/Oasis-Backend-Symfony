<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route; // 🛑 Assure-toi d'utiliser Attribute\Route

// 👇 NOUVEAUX IMPORTS POUR LE MAIL
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class StripeWebhookController extends AbstractController
{
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(
        Request $request,
        ReservationRepository $reservationRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer // 👈 On injecte le Mailer ici
    ): Response {
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Payload invalide', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Signature invalide', 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $reservationId = $paymentIntent->metadata->reservation_id ?? null;

            if ($reservationId) {
                $reservation = $reservationRepo->find($reservationId);

                if ($reservation) {
                    // 1. On valide le paiement en BDD
                    $reservation->setIsPaid(true);
                    $em->flush();

                    // 👇 2. ON ENVOIE L'EMAIL
                    $email = (new TemplatedEmail())
                        ->from(new Address('contact@loasis.com', 'Domaine L\'Oasis'))
                        ->to($reservation->getUser()->getEmail())
                        ->subject('Confirmation de votre séjour n°' . $reservation->getId())
                        ->htmlTemplate('emails/confirmation.html.twig')
                        ->context([
                            'reservation' => $reservation,
                            'user' => $reservation->getUser()
                        ]);

                    $mailer->send($email);
                }
            }
        }

        return new Response('Webhook et Email traités', 200);
    }
}
