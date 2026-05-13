<?php

namespace App\Service;

use App\Entity\Liste;
use App\Entity\Bareme;
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
     * Retourne également l'ID et le libellé du barème utilisé
     */
    public function calculateMontantWithBareme(Liste $adherent, ?\DateTimeInterface $date = null): array
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
            return [
                'montant' => $bareme->getMontant(),
                'baremeId' => $bareme->getId(),
                'baremeLibelle' => $this->getBaremeLibelle($bareme)
            ];
        }

        // Fallback : barème par défaut
        $montant = $this->getDefaultMontant($categorie, $sousCategorie);
        return [
            'montant' => $montant,
            'baremeId' => null,
            'baremeLibelle' => 'Barème par défaut'
        ];
    }

    /**
     * Calcule le montant uniquement (pour compatibilité)
     */
    public function calculateMontant(Liste $adherent, ?\DateTimeInterface $date = null): float
    {
        $result = $this->calculateMontantWithBareme($adherent, $date);
        return $result['montant'];
    }

    /**
     * Récupère le montant historique d'une cotisation (ne recalcule jamais)
     */
    public function getMontantHistorique(Liste $adherent, string $periode): float
    {
        // Vérifier si une cotisation existe déjà avec un montant stocké
        foreach ($adherent->getCotisations() as $cotisation) {
            if ($cotisation->getPeriode() === $periode) {
                // Retourner le montant stocké, ne jamais recalculer
                return $cotisation->getMontant();
            }
        }
        
        // Si aucune cotisation n'existe, calculer avec barème
        $result = $this->calculateMontantWithBareme($adherent, \DateTime::createFromFormat('Y', $periode));
        return $result['montant'];
    }

    /**
     * Vérifie si une cotisation existe déjà pour une période
     */
    public function cotisationExists(Liste $adherent, string $periode): bool
    {
        foreach ($adherent->getCotisations() as $cotisation) {
            if ($cotisation->getPeriode() === $periode) {
                return true;
            }
        }
        return false;
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

    private function getBaremeLibelle(Bareme $bareme): string
    {
        if ($bareme->getCategorie() === 'entreprise') {
            $tranches = [
                '1-10' => '1 à 10 employés',
                '11-50' => '11 à 50 employés',
                '51+' => 'Plus de 50 employés'
            ];
            return 'Entreprise - ' . ($tranches[$bareme->getSousCategorie()] ?? $bareme->getSousCategorie());
        } elseif ($bareme->getCategorie() === 'ong') {
            return 'ONG / Association';
        } else {
            return 'Membre sponsor';
        }
    }
}