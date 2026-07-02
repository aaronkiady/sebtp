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
            'updated' => 0,
            'messages' => []
        ];

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            array_shift($rows);

            foreach ($rows as $rowIndex => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $nom = $this->getValue($row, 3);
                if (empty($nom)) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - Le nom de l'adhérent est obligatoire.";
                    continue;
                }

                try {
                    $existingAdherent = $this->entityManager
                        ->getRepository(Liste::class)
                        ->findOneBy(['nom' => $nom]);

                    if ($existingAdherent) {
                        $adherent = $existingAdherent;
                        $isUpdate = true;
                    } else {
                        $adherent = new Liste();
                        $isUpdate = false;
                    }

                    // Map des colonnes
                    $adherent->setEmail($this->getValue($row, 0));
                    $adherent->setNumero($this->getValue($row, 1));
                    $adherent->setAdresse($this->getValue($row, 2));
                    $adherent->setNom($nom);
                    $adherent->setSiteWeb($this->getValue($row, 4));
                    $adherent->setActivite($this->getValue($row, 5) ?? 'Non renseigné');
                    
                    $filiereValue = $this->getValue($row, 6);
                    if ($filiereValue) {
                        if (strpos($filiereValue, ',') !== false) {
                            $filiereArray = array_map('trim', explode(',', $filiereValue));
                        } else {
                            $filiereArray = [$filiereValue];
                        }
                        $adherent->setFiliere($filiereArray);
                    } else {
                        $adherent->setFiliere([]);
                    }
                    
                    $adherent->setNbEmployes($this->getValue($row, 7) ?? '0');
                    
                    $cotFmtp = $this->getValue($row, 8);
                    $adherent->setCotFMTP($cotFmtp === 'oui' ? 'Oui' : ($cotFmtp === 'non' ? 'Non' : 'Non'));
                    
                    $adherent->setDg($this->getValue($row, 9) ?? 'Non renseigné');
                    $adherent->setAdresseDg($this->getValue($row, 10));
                    $adherent->setTelephoneDg($this->getValue($row, 11));
                    
                    $statutMenmbre = $this->getValue($row, 12);
                    if ($statutMenmbre === 'simple') {
                        $adherent->setStatutMenmbre('simple');
                    } elseif ($statutMenmbre === 'bureau') {
                        $adherent->setStatutMenmbre('bureau');
                    } else {
                        $adherent->setStatutMenmbre('simple');
                    }
                    
                    $adherent->setFonctionSEBTP($this->getValue($row, 13));
                    $adherent->setMandat($this->getValue($row, 14));
                    
                    $statut = $this->getStatutValue($this->getValue($row, 15));
                    $adherent->setStatut($statut ?? 'actif');
                    
                    $adherent->setObservation($this->getValue($row, 16));
                    $adherent->setRaisonDepart($this->getValue($row, 17));
                    $adherent->setStatutDemande($this->getValue($row, 18));
                    
                    // CORRECTION : Convertir les dates en DateTime pour validationBureau
                    $validationBureau = $this->getValue($row, 19);
                    if ($validationBureau) {
                        $dateTime = $this->convertToDateTime($validationBureau);
                        if ($dateTime) {
                            $adherent->setValidationBureau($dateTime);
                        }
                    }
                    
                    // CORRECTION : Convertir les dates en DateTime pour validationAG
                    $validationAG = $this->getValue($row, 20);
                    if ($validationAG) {
                        $dateTime = $this->convertToDateTime($validationAG);
                        if ($dateTime) {
                            $adherent->setValidationAG($dateTime);
                        }
                    }
                    
                    $type = $this->getTypeValue($this->getValue($row, 21));
                    $adherent->setType($type ?? 'entreprise');
                    
                    $adherent->setFichiers($this->getValue($row, 22));

                    // Nouveaux champs
                    $adherent->setNif($this->getValue($row, 23));
                    $adherent->setStat($this->getValue($row, 24));
                    $adherent->setCnaps($this->getValue($row, 25));

                    if (!$isUpdate) {
                        $this->entityManager->persist($adherent);
                        $results['success']++;
                        $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Adhérent '{$adherent->getNom()}' importé avec succès.";
                    } else {
                        $results['updated']++;
                        $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Adhérent '{$adherent->getNom()}' mis à jour avec succès.";
                    }

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

    /**
     * Convertit une date en objet DateTime
     * Supporte les formats : JJ/MM/AAAA, DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD
     */
    private function convertToDateTime(?string $date): ?\DateTimeInterface
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);
        
        // Format JJ/MM/AAAA (ex: 02/07/2026)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            $dateTime = \DateTime::createFromFormat('d/m/Y', $date);
            if ($dateTime) {
                return $dateTime;
            }
        }
        
        // Format DD-MM-YYYY (ex: 02-07-2026)
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches)) {
            $dateTime = \DateTime::createFromFormat('d-m-Y', $date);
            if ($dateTime) {
                return $dateTime;
            }
        }
        
        // Format YYYY-MM-DD (ex: 2026-07-02)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
            if ($dateTime) {
                return $dateTime;
            }
        }
        
        // Format YYYY/MM/DD (ex: 2026/07/02)
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date, $matches)) {
            $dateTime = \DateTime::createFromFormat('Y/m/d', $date);
            if ($dateTime) {
                return $dateTime;
            }
        }
        
        // Essayer de créer une date automatiquement
        try {
            return new \DateTime($date);
        } catch (\Exception $e) {
            return null;
        }
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
}