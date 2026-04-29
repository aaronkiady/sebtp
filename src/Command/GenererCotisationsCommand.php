<?php

namespace App\Command;

use App\Entity\Cotisation;
use App\Repository\ListeRepository;
use App\Service\CotisationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generer-cotisations',
    description: 'Génère les cotisations pour une année donnée'
)]
class GenererCotisationsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ListeRepository $listeRepository,
        private CotisationCalculator $cotisationCalculator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('annee', InputArgument::REQUIRED, 'Année pour laquelle générer les cotisations (ex: 2025)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $annee = $input->getArgument('annee');

        // Récupérer tous les adhérents actifs
        $adherents = $this->listeRepository->findBy(['statut' => 'actif']);
        $compteur = 0;

        foreach ($adherents as $adherent) {
            // Vérifier si une cotisation existe déjà pour cette année
            $existing = $this->entityManager
                ->getRepository(Cotisation::class)
                ->findOneBy([
                    'adherent' => $adherent,
                    'periode' => $annee
                ]);

            if (!$existing) {
                $montant = $this->cotisationCalculator->calculateMontant($adherent);
                
                $cotisation = new Cotisation();
                $cotisation->setAdherent($adherent);
                $cotisation->setPeriode($annee);
                $cotisation->setMontant($montant);
                $cotisation->setMontantPaye(0);
                $cotisation->setStatut('impaye');
                
                $this->entityManager->persist($cotisation);
                $compteur++;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d cotisations générées pour l\'année %s', $compteur, $annee));
        
        return Command::SUCCESS;
    }
}