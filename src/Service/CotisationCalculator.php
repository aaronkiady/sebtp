<?php

namespace App\Service;

use App\Entity\Liste;

class CotisationCalculator
{
    // Barème des cotisations
    private const BAREME = [
        'entreprise' => [
            '1-10' => 200000,
            '11-50' => 400000,
            '51+' => 1000000,
        ],
        'ong' => 400000,
        'sponsor' => 10000000,
    ];

    public function calculateMontant(Liste $adherent): float
    {
        // Membre sponsor
        if ($adherent->getStatutMenmbre() === 'sponsor') {
            return self::BAREME['sponsor'];
        }

        // Vérifier si c'est une ONG via le champ type ou activité
        if ($this->isONG($adherent)) {
            return self::BAREME['ong'];
        }

        // Entreprise : calcul basé sur le nombre d'employés
        return $this->calculateEntrepriseMontant($adherent);
    }

    private function isONG(Liste $adherent): bool
    {
        // Vérifier via le champ type
        if ($adherent->getType() === 'ong') {
            return true;
        }
        
        // Vérifier via l'activité ou le nom
        $activite = strtolower($adherent->getActivite() ?? '');
        $nom = strtolower($adherent->getNom() ?? '');
        
        return str_contains($activite, 'ong') 
            || str_contains($activite, 'association')
            || str_contains($nom, 'ong')
            || str_contains($nom, 'association');
    }

    private function calculateEntrepriseMontant(Liste $adherent): float
    {
        $nbEmployes = $this->parseEmployesNumber($adherent->getNbEmployes());
        
        if ($nbEmployes <= 10) {
            return self::BAREME['entreprise']['1-10'];
        } elseif ($nbEmployes <= 50) {
            return self::BAREME['entreprise']['11-50'];
        } else {
            return self::BAREME['entreprise']['51+'];
        }
    }

    private function parseEmployesNumber(?string $nbEmployes): int
    {
        if (empty($nbEmployes)) {
            return 0;
        }
        $cleaned = preg_replace('/[^0-9]/', '', $nbEmployes);
        return (int) $cleaned;
    }

    public function getTrancheEmployes(?string $nbEmployes): string
    {
        $nb = $this->parseEmployesNumber($nbEmployes);
        
        if ($nb <= 10) {
            return '1 à 10 employés';
        } elseif ($nb <= 50) {
            return '11 à 50 employés';
        } else {
            return 'Plus de 50 employés';
        }
    }

    public function getMontantFormate(Liste $adherent): string
    {
        return number_format($this->calculateMontant($adherent), 0, '.', ' ') . ' MGA';
    }

    public function getBareme(): array
    {
        return [
            'entreprise' => [
                ['tranche' => '1 à 10 employés', 'montant' => self::BAREME['entreprise']['1-10'], 'montant_formate' => number_format(self::BAREME['entreprise']['1-10'], 0, '.', ' ') . ' MGA'],
                ['tranche' => '11 à 50 employés', 'montant' => self::BAREME['entreprise']['11-50'], 'montant_formate' => number_format(self::BAREME['entreprise']['11-50'], 0, '.', ' ') . ' MGA'],
                ['tranche' => 'Plus de 50 employés', 'montant' => self::BAREME['entreprise']['51+'], 'montant_formate' => number_format(self::BAREME['entreprise']['51+'], 0, '.', ' ') . ' MGA'],
            ],
            'ong' => ['montant' => self::BAREME['ong'], 'montant_formate' => number_format(self::BAREME['ong'], 0, '.', ' ') . ' MGA'],
            'sponsor' => ['montant' => self::BAREME['sponsor'], 'montant_formate' => number_format(self::BAREME['sponsor'], 0, '.', ' ') . ' MGA'],
        ];
    }
}