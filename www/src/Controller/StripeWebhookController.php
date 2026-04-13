<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Repository\InvoiceRepository;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class StripeWebhookController extends AbstractController
{
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(
        Request $request,
        ReservationRepository $reservationRepo,
        InvoiceRepository $invoiceRepo,
        PdfService $pdfService,
        Environment $twig,
        EntityManagerInterface $em,
        MailerInterface $mailer
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

            // 👇 ON MET TOUT DANS UN BLOC TRY POUR ATTRAPER L'ERREUR
            try {
                $paymentIntent = $event->data->object;

                $reservationId = $paymentIntent->metadata->reservation_id ?? null;
                $invoiceId = $paymentIntent->metadata->invoice_id ?? null;

                if ($reservationId) {
                    $reservation = $reservationRepo->find($reservationId);

                    if ($reservation) {
                        $reservation->setIsPaid(true);

                        $fullPdfPath = null;

                        if ($invoiceId) {
                            $invoice = $invoiceRepo->find($invoiceId);
                            if ($invoice) {
                                $html = $twig->render('invoice/pdf.html.twig', [
                                    'invoice' => $invoice,
                                    'reservation' => $reservation
                                ]);

                                $filename = $invoice->getTitle() . '.pdf';
                                $pdfPathRelative = $pdfService->generateAndSavePdf($html, $filename);

                                $invoice->setPath($pdfPathRelative);
                                $fullPdfPath = __DIR__ . '/../../public' . $pdfPathRelative;
                            }
                        }

                        $em->flush();

                        $email = (new TemplatedEmail())
                            ->from(new Address('contact@loasis.com', 'Domaine L\'Oasis'))
                            ->to($reservation->getUser()->getEmail())
                            ->subject('Confirmation de votre séjour n°' . $reservation->getId())
                            ->htmlTemplate('emails/confirmation.html.twig')
                            ->context([
                                'reservation' => $reservation,
                                'user' => $reservation->getUser()
                            ]);

                        if ($fullPdfPath && file_exists($fullPdfPath)) {
                            $email->attachFromPath($fullPdfPath, 'Facture_' . $reservation->getId() . '.pdf', 'application/pdf');
                        }

                        $mailer->send($email);
                    }
                }
            } catch (\Throwable $e) {
                // 🚨 C'EST ICI QU'ON CRÉE LE FICHIER TEXTE AVEC L'ERREUR !
                $errorMsg = "ERREUR STRIPE : " . $e->getMessage() . "\nFichier : " . $e->getFile() . " (Ligne " . $e->getLine() . ")";
                file_put_contents(__DIR__ . '/../../erreur_stripe.txt', $errorMsg);

                return new Response('Erreur interne interceptée', 500);
            }
        }

        return new Response('Webhook, PDF et Email traités', 200);
    }
}
