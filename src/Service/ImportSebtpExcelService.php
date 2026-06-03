<?php

namespace App\Service;

use App\Entity\Sebtp;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportSebtpExcelService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function importSebtps(UploadedFile $file): array
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

                // Vérifier si le nom de l'organisme est présent (obligatoire)
                $nomOrganisme = $this->getValue($row, 1); // Colonne B = nom_organisme
                if (empty($nomOrganisme)) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - Le nom de l'organisme est obligatoire.";
                    continue;
                }

                try {
                    // Créer un NOUVEL organisme SEBTP
                    $sebtp = new Sebtp();

                    // Map des colonnes selon votre template
                    // Colonne A (index 0) = instance
                    $sebtp->setInstance($this->getValue($row, 0) ?? 'Non renseigné');
                    
                    // Colonne B (index 1) = nom_organisme (obligatoire)
                    $sebtp->setNomOrganisme($nomOrganisme);
                    
                    // Colonne C (index 2) = mandat
                    $sebtp->setMandat($this->getValue($row, 2));
                    
                    // Colonne D (index 3) = nom_representant
                    $sebtp->setNomRepresentant($this->getValue($row, 3));
                    
                    // Colonne E (index 4) = observation
                    $sebtp->setObservation($this->getValue($row, 4));
                    
                    // Colonne F (index 5) = fichiers (optionnel)
                    // $sebtp->setFichiers($this->getValue($row, 5));

                    $this->entityManager->persist($sebtp);
                    $results['success']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Organisme '{$sebtp->getNomOrganisme()}' importé avec succès.";

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
}