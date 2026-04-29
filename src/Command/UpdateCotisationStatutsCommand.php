<?php

namespace App\Command;

use App\Repository\CotisationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-cotisation-statuts',
    description: 'Met à jour les statuts des cotisations (payé/partiel/impayé)'
)]
class UpdateCotisationStatutsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CotisationRepository $cotisationRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $cotisations = $this->cotisationRepository->findAll();
        $compteur = 0;

        foreach ($cotisations as $cotisation) {
            $oldStatut = $cotisation->getStatut();
            $cotisation->updateStatut(); // Met à jour le statut selon montantPaye
            
            if ($oldStatut !== $cotisation->getStatut()) {
                $compteur++;
            }
        }

        $this->entityManager->flush();
        
        $io->success(sprintf('%d cotisations ont été mises à jour', $compteur));
        
        return Command::SUCCESS;
    }
}