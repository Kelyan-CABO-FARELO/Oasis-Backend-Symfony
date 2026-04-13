<?php

namespace App\Command;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:invoices:check-old',
    description: 'Vérifie et alerte sur les factures de plus de 3 ans (RGPD).',
)]
class CheckOldInvoicesCommand extends Command
{
    private $invoiceRepo;
    private $em;

    public function __construct(InvoiceRepository $invoiceRepo, EntityManagerInterface $em)
    {
        parent::__construct();
        $this->invoiceRepo = $invoiceRepo;
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. On calcule la date d'il y a 3 ans exactement
        $threeYearsAgo = new \DateTime('-3 years');

        // 2. On interroge la base de données pour trouver les vieilles factures
        $oldInvoices = $this->invoiceRepo->createQueryBuilder('i')
            ->where('i.createdAt <= :limitDate')
            ->setParameter('limitDate', $threeYearsAgo)
            ->getQuery()
            ->getResult();

        // 3A. S'il n'y en a pas, on affiche un message de succès
        if (count($oldInvoices) === 0) {
            $io->success("Aucune facture de plus de 3 ans trouvée. Ton domaine est en règle avec le RGPD !");
            return Command::SUCCESS;
        }

        // 3B. S'il y en a, on déclenche l'alerte !
        $io->warning(count($oldInvoices) . " facture(s) ont plus de 3 ans et doivent être archivées ou supprimées !");

        // On liste les factures concernées dans la console
        foreach ($oldInvoices as $invoice) {
            $io->text(sprintf(
                "- [%s] Facture n°%d : %s (Client: %s)",
                $invoice->getCreatedAt()->format('Y-m-d'),
                $invoice->getId(),
                $invoice->getTitle(),
                $invoice->getPerson()
            ));

            // 💡 Pour plus tard : C'est ici qu'on pourra ajouter le code pour supprimer l'entrée
            // de la BDD ou archiver le PDF dans un dossier protégé !
        }

        $io->note("Pensez à nettoyer ces factures depuis le panel d'administration.");

        return Command::SUCCESS;
    }
}
