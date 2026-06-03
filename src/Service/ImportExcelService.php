<?php

namespace App\Service;

use App\Entity\Liste;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportExcelService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function importAdherents(UploadedFile $file): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'messages' => []
        ];

        try {
            // Charger le fichier Excel
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Supprimer la ligne d'en-tête
            array_shift($rows);

            foreach ($rows as $rowIndex => $row) {
                // Vérifier si la ligne est vide
                if (empty(array_filter($row))) {
                    continue;
                }

                // Vérifier si le nom est présent (obligatoire)
                $nom = $this->getValue($row, 3);
                if (empty($nom)) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - Le nom de l'adhérent est obligatoire.";
                    continue;
                }

                try {
                    // Créer un NOUVEL adhérent (pas de recherche d'existant)
                    $adherent = new Liste();

                    // Map des colonnes selon votre template
                    $adherent->setEmail($this->getValue($row, 0));
                    $adherent->setNumero($this->getValue($row, 1));
                    $adherent->setAdresse($this->getValue($row, 2));
                    $adherent->setNom($nom);
                    $adherent->setSiteWeb($this->getValue($row, 4));
                    
                    // Valeurs par défaut si non renseignées
                    $adherent->setActivite($this->getValue($row, 5) ?? 'Non renseigné');
                    $adherent->setFiliere($this->getValue($row, 6) ?? 'BTP / Construction ');
                    $adherent->setNbEmployes($this->getValue($row, 7) ?? '0');
                    
                    // Cotisation FMFP
                    $cotFmtp = $this->getValue($row, 8);
                    $adherent->setCotFMTP($cotFmtp === 'oui' ? 'Oui' : ($cotFmtp === 'non' ? 'Non' : 'Non'));
                    
                    $adherent->setDg($this->getValue($row, 9) ?? 'Non renseigné');
                    $adherent->setAdresseDg($this->getValue($row, 10));
                    $adherent->setTelephoneDg($this->getValue($row, 11));
                    
                    // Statut membre
                    $statutMenmbre = $this->getValue($row, 12);
                    if ($statutMenmbre === 'simple') {
                        $adherent->setStatutMenmbre('simple');
                    } elseif ($statutMenmbre === 'bureau') {
                        $adherent->setStatutMenmbre('bureau');
                    } else {
                        $adherent->setStatutMenmbre('simple'); // Valeur par défaut
                    }
                    
                    $adherent->setFonctionSEBTP($this->getValue($row, 13));
                    $adherent->setMandat($this->getValue($row, 14));
                    
                    // Statut principal
                    $statut = $this->getStatutValue($this->getValue($row, 15));
                    $adherent->setStatut($statut ?? 'actif');
                    
                    $adherent->setObservation($this->getValue($row, 16));
                    $adherent->setRaisonDepart($this->getValue($row, 17));
                    $adherent->setStatutDemande($this->getValue($row, 18));
                    
                    // Dates
                    $validationBureau = $this->getValue($row, 19);
                    if ($validationBureau && $this->isValidDate($validationBureau)) {
                        $adherent->setValidationBureau($validationBureau);
                    }
                    
                    $validationAG = $this->getValue($row, 20);
                    if ($validationAG && $this->isValidDate($validationAG)) {
                        $adherent->setValidationAG($validationAG);
                    }
                    
                    $type = $this->getTypeValue($this->getValue($row, 21));
                    $adherent->setType($type ?? 'entreprise');
                    
                    // Fichiers (optionnel)
                    $adherent->setFichiers($this->getValue($row, 22));

                    $this->entityManager->persist($adherent);
                    $results['success']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Adhérent '{$adherent->getNom()}' importé avec succès.";

                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - " . $e->getMessage();
                }
            }

            $this->entityManager->flush();
            
        } catch (\Exception $e) {
            $results['errors']++;
            $results['messages'][] = "Erreur générale : " . $e->getMessage();
        }
        
        return $results;
    }

    private function getValue(array $row, int $index): ?string
    {
        return isset($row[$index]) && !empty(trim($row[$index])) ? trim($row[$index]) : null;
    }

    private function getStatutValue(?string $statut): ?string
    {
        if (!$statut) return null;
        
        $statut = strtolower(trim($statut));
        $mapping = [
            'actif' => 'actif',
            'adhéré' => 'actif',
            'adhere' => 'actif',
            'inactif' => 'inactif',
            'radié' => 'radie',
            'radie' => 'radie',
            'demande' => 'demande',
            'demande d\'adhésion' => 'demande',
        ];
        
        return $mapping[$statut] ?? 'actif';
    }

    private function getTypeValue(?string $type): ?string
    {
        if (!$type) return null;
        
        $type = strtolower(trim($type));
        $mapping = [
            'ong' => 'ong',
            'entreprise' => 'entreprise',
            'simple entreprise' => 'entreprise',
            'sponsor' => 'sponsor',
        ];
        
        return $mapping[$type] ?? 'entreprise';
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}