<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Invoice;
use App\Entity\LineInvoice;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MakeOwnerController extends AbstractController
{
    #[Route('/api/users/{id}/make-owner', name: 'api_make_owner', methods: ['POST'])]
    public function __invoke(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $amountInEuros = (int) ($data['amount'] ?? 0);
        $productId = $data['productId'] ?? null;

        if ($amountInEuros <= 0 || !$productId) {
            return $this->json(['message' => 'Montant ou produit invalide.'], 400);
        }

        $product = $productRepo->find($productId);
        if (!$product) {
            return $this->json(['message' => 'Ce bien n\'existe pas.'], 404);
        }

        // 1. On transforme le prospect en Propriétaire officiel
        $user->setIsOwner(true);
        $user->setWantsToBecomeOwner(false); // 👈 Il disparaît de la file d'attente
        $user->setRoles(['ROLE_USER', 'ROLE_OWNER']);
        $user->setContractDate(new \DateTime());

        // On lie le mobil-home/emplacement à l'utilisateur
        $user->addProduct($product);

        // 2. On génère sa facture d'achat de vente
        $invoice = new Invoice();
        $invoice->setTitle('FA-' . date('Ymd') . '-' . random_int(1000, 9999));
        $invoice->setPerson($user->getFirstname() . ' ' . $user->getLastname());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setPath('generation_en_attente');

        $line = new LineInvoice();
        $line->setLineProduct('Achat Résidence/Parcelle : ' . $product->getTitle());
        $line->setLinePrice($amountInEuros * 100); // En centimes pour Stripe
        $invoice->addLineInvoice($line);

        $em->persist($line);
        $em->persist($invoice);

        // On sauvegarde tout
        $em->flush();

        // 3. On prépare la machine à carte bleue (Stripe)
        Stripe::setApiKey(trim($_ENV['STRIPE_SECRET_KEY']));

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInEuros * 100,
                'currency' => 'eur',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'user_id' => $user->getId(),
                    'invoice_id' => $invoice->getId(),
                    'type' => 'owner_purchase' // Permettra au webhook de différencier
                ],
            ]);

            return $this->json([
                'clientSecret' => $paymentIntent->client_secret,
                'message' => 'Prêt pour le paiement'
            ], 200);

        } catch (\Exception $e) {
            return $this->json(['message' => $e->getMessage()], 500);
        }
    }
}
