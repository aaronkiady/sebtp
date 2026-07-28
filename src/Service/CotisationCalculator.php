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
     * Calcule le montant de la cotisation en fonction de l'adhérent et de la période (année)
     */
    public function calculateMontantByPeriode(Liste $adherent, $periode): float
    {
        $date = $this->createDateFromPeriode($periode);
        return $this->calculateMontantWithBareme($adherent, $date)['montant'];
    }

    /**
     * Calcule le montant de la cotisation en fonction de l'adhérent et de la date
     * Retourne également l'ID et le libellé du barème utilisé
     * 
     * Règles de calcul :
     * - SPONSOR : catégorie 'sponsor' (identifié par $adherent->getType() === 'sponsor')
     * - ONG : catégorie 'ong' (identifié par $adherent->getType() === 'ong')
     * - ENTREPRISE : catégorie 'entreprise' + sous-catégorie basée sur nbEmployes
     */
    public function calculateMontantWithBareme(Liste $adherent, ?\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTime();
        
        // Déterminer la catégorie et la sous-catégorie
        // CORRECTION : Utiliser getType() pour identifier les sponsors, pas getStatutMenmbre()
        if ($adherent->getType() === 'sponsor') {
            $categorie = 'sponsor';
            $sousCategorie = null;
        } elseif ($this->isONG($adherent)) {
            $categorie = 'ong';
            $sousCategorie = null;
        } else {
            // Entreprise : dépend de la tranche d'employés
            $categorie = 'entreprise';
            $sousCategorie = $this->getTrancheEmployesKey($adherent->getNbEmployes());
        }

        // Rechercher un barème actif pour cette catégorie/sous-catégorie
        $bareme = $this->baremeRepository->getBaremeActif($categorie, $sousCategorie, $date);
        
        if ($bareme) {
            return [
                'montant' => $bareme->getMontant(),
                'baremeId' => $bareme->getId(),
                'baremeLibelle' => $this->getBaremeLibelle($bareme),
                'periode' => $date->format('Y')
            ];
        }

        // Fallback : montant par défaut si aucun barème trouvé
        $montant = $this->getDefaultMontant($categorie, $sousCategorie);
        return [
            'montant' => $montant,
            'baremeId' => null,
            'baremeLibelle' => 'Barème par défaut',
            'periode' => $date->format('Y')
        ];
    }

    /**
     * Calcule le montant uniquement (pour compatibilité)
     */
    public function calculateMontant(Liste $adherent, $periode = null): float
    {
        if ($periode === null) {
            $date = new \DateTime();
        } elseif ($periode instanceof \DateTimeInterface) {
            $date = $periode;
        } else {
            $date = $this->createDateFromPeriode($periode);
        }
        
        $result = $this->calculateMontantWithBareme($adherent, $date);
        return $result['montant'];
    }

    /**
     * Récupère le montant historique d'une cotisation (ne recalcule jamais)
     */
    public function getMontantHistorique(Liste $adherent, string $periode): float
    {
        foreach ($adherent->getCotisations() as $cotisation) {
            if ($cotisation->getPeriode() === $periode) {
                return $cotisation->getMontant();
            }
        }
        
        $date = $this->createDateFromPeriode($periode);
        $result = $this->calculateMontantWithBareme($adherent, $date);
        return $result['montant'];
    }

    /**
     * Récupère le barème complet pour une période donnée
     */
    public function getBaremeForPeriode(Liste $adherent, string $periode): ?array
    {
        $date = $this->createDateFromPeriode($periode);
        
        // CORRECTION : Utiliser getType() pour identifier les sponsors
        if ($adherent->getType() === 'sponsor') {
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
                'id' => $bareme->getId(),
                'montant' => $bareme->getMontant(),
                'libelle' => $this->getBaremeLibelle($bareme),
                'date_debut' => $bareme->getDateDebut()->format('d/m/Y'),
                'date_fin' => $bareme->getDateFin() ? $bareme->getDateFin()->format('d/m/Y') : 'En cours'
            ];
        }
        
        return null;
    }

    /**
     * Crée un objet DateTime à partir d'une année
     */
    public function createDateFromPeriode($periode): \DateTimeInterface
    {
        if ($periode instanceof \DateTimeInterface) {
            return $periode;
        }
        
        $year = (int) $periode;
        $date = \DateTime::createFromFormat('Y-m-d', $year . '-01-01');
        if (!$date) {
            $date = new \DateTime();
        }
        return $date;
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

    /**
     * Vérifie si l'adhérent est une ONG
     */
    private function isONG(Liste $adherent): bool
    {
        if ($adherent->getType() === 'ong') {
            return true;
        }
        $activite = strtolower($adherent->getActivite() ?? '');
        $nom = strtolower($adherent->getNom() ?? '');
        return str_contains($activite, 'ong') || str_contains($nom, 'ong');
    }

    /**
     * Retourne la clé de la tranche d'employés pour la recherche en base de données
     * Uniquement utilisé pour les ENTREPRISES
     */
    private function getTrancheEmployesKey(?string $nbEmployes): string
    {
        $nb = $this->parseEmployesNumber($nbEmployes);
        if ($nb <= 10) return '1-10';
        if ($nb <= 50) return '11-50';
        return '51+';
    }

    /**
     * Retourne le libellé lisible de la tranche d'employés
     * Uniquement utilisé pour les ENTREPRISES
     */
    public function getTrancheEmployes(?string $nbEmployes): string
    {
        $nb = $this->parseEmployesNumber($nbEmployes);
        if ($nb <= 10) return '1 à 10 employés';
        if ($nb <= 50) return '11 à 50 employés';
        return 'Plus de 50 employés';
    }

    /**
     * Extrait le nombre d'employés d'une chaîne
     */
    private function parseEmployesNumber(?string $nbEmployes): int
    {
        if (empty($nbEmployes)) return 0;
        return (int) preg_replace('/[^0-9]/', '', $nbEmployes);
    }

    /**
     * Montants par défaut (fallback si aucun barème n'est trouvé)
     */
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
     * Génère le libellé d'un barème
     */
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

    /**
     * Retourne tous les barèmes actifs pour affichage
     */
    public function getBaremeComplete(): array
    {
        $baremes = $this->baremeRepository->getAllActifs();
        $result = [];
        
        foreach ($baremes as $bareme) {
            $key = $bareme->getCategorie();
            if ($bareme->getSousCategorie()) {
                $key .= '_' . $bareme->getSousCategorie();
            }
            
            $result[$key] = [
                'categorie' => $bareme->getCategorie(),
                'sousCategorie' => $bareme->getSousCategorie(),
                'montant' => $bareme->getMontant(),
                'montant_formate' => number_format($bareme->getMontant(), 0, '.', ' ') . ' MGA',
                'libelle' => $this->getBaremeLibelle($bareme),
                'date_debut' => $bareme->getDateDebut()->format('d/m/Y'),
                'actif' => $bareme->isActif()
            ];
        }
        
        return $result;
    }
}