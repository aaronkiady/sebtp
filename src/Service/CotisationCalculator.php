<?php

namespace App\Service;

use App\Entity\Liste;
use App\Repository\BaremeRepository;
use Doctrine\ORM\EntityManagerInterface;

class CotisationCalculator
{
    private BaremeRepository $baremeRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(BaremeRepository $baremeRepository, EntityManagerInterface $entityManager)
    {
        $this->baremeRepository = $baremeRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Calcule le montant de la cotisation en fonction de l'adhérent et de la date
     */
    public function calculateMontant(Liste $adherent, ?\DateTimeInterface $date = null): float
    {
        $date = $date ?? new \DateTime();
        
        // Déterminer la catégorie et sous-catégorie
        if ($adherent->getStatutMenmbre() === 'sponsor') {
            $categorie = 'sponsor';
            $sousCategorie = null;
        } elseif ($this->isONG($adherent)) {
            $categorie = 'ong';
            $sousCategorie = null;
        } else {
            $categorie = 'entreprise';
            $sousCategorie = $this->getTrancheEmployesKey($adherent->getNbEmployes());
        }

        $bareme = $this->baremeRepository->getBaremeActif($categorie, $sousCategorie, $date);
        
        if ($bareme) {
            return $bareme->getMontant();
        }

        // Fallback : barème par défaut si aucun barème trouvé
        return $this->getDefaultMontant($categorie, $sousCategorie);
    }

    /**
     * Récupère le barème pour une cotisation existante (historique)
     */
    public function getMontantHistorique(Liste $adherent, string $periode): float
    {
        // Vérifier si une cotisation existe déjà avec un montant
        foreach ($adherent->getCotisations() as $cotisation) {
            if ($cotisation->getPeriode() === $periode) {
                return $cotisation->getMontant();
            }
        }
        
        // Sinon calculer avec la date de la période
        $date = \DateTime::createFromFormat('Y', $periode);
        return $this->calculateMontant($adherent, $date);
    }

    private function isONG(Liste $adherent): bool
    {
        if ($adherent->getType() === 'ong') {
            return true;
        }
        $activite = strtolower($adherent->getActivite() ?? '');
        $nom = strtolower($adherent->getNom() ?? '');
        return str_contains($activite, 'ong') || str_contains($nom, 'ong');
    }

    private function getTrancheEmployesKey(?string $nbEmployes): string
    {
        $nb = $this->parseEmployesNumber($nbEmployes);
        if ($nb <= 10) return '1-10';
        if ($nb <= 50) return '11-50';
        return '51+';
    }

    /**
     * Récupère la tranche d'employés pour l'affichage
     */
    public function getTrancheEmployes(?string $nbEmployes): string
    {
        $nb = $this->parseEmployesNumber($nbEmployes);
        if ($nb <= 10) return '1 à 10 employés';
        if ($nb <= 50) return '11 à 50 employés';
        return 'Plus de 50 employés';
    }

    private function parseEmployesNumber(?string $nbEmployes): int
    {
        if (empty($nbEmployes)) return 0;
        return (int) preg_replace('/[^0-9]/', '', $nbEmployes);
    }

    private function getDefaultMontant(string $categorie, ?string $sousCategorie = null): float
    {
        $defaults = [
            'entreprise' => [
                '1-10' => 200000,
                '11-50' => 400000,
                '51+' => 1000000,
            ],
            'ong' => 400000,
            'sponsor' => 10000000,
        ];

        if ($categorie === 'entreprise' && $sousCategorie) {
            return $defaults['entreprise'][$sousCategorie] ?? 400000;
        }

        return $defaults[$categorie] ?? 400000;
    }

    /**
     * Initialise les barèmes par défaut
     */
    public function initDefaultBaremes(): void
    {
        // Vérifier si des barèmes existent déjà
        $existing = $this->baremeRepository->findAll();
        if (!empty($existing)) {
            return;
        }

        $defaults = [
            ['categorie' => 'entreprise', 'sousCategorie' => '1-10', 'montant' => 200000, 'description' => 'Entreprise 1-10 employés'],
            ['categorie' => 'entreprise', 'sousCategorie' => '11-50', 'montant' => 400000, 'description' => 'Entreprise 11-50 employés'],
            ['categorie' => 'entreprise', 'sousCategorie' => '51+', 'montant' => 1000000, 'description' => 'Entreprise +50 employés'],
            ['categorie' => 'ong', 'sousCategorie' => null, 'montant' => 400000, 'description' => 'ONG / Association'],
            ['categorie' => 'sponsor', 'sousCategorie' => null, 'montant' => 10000000, 'description' => 'Membre sponsor'],
        ];

        foreach ($defaults as $default) {
            $bareme = new Bareme();
            $bareme->setCategorie($default['categorie']);
            $bareme->setSousCategorie($default['sousCategorie']);
            $bareme->setMontant($default['montant']);
            $bareme->setDescription($default['description']);
            $bareme->setDateDebut(new \DateTime('2024-01-01'));
            $bareme->setActif(true);
            
            $this->entityManager->persist($bareme);
        }
        
        $this->entityManager->flush();
    }
}