<?php

namespace App\Service;

use App\Entity\Cotisation;
use App\Entity\Liste;
use App\Entity\Paiement;
use App\Repository\ListeRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportCotisationExcelService
{
    private EntityManagerInterface $entityManager;
    private ListeRepository $listeRepository;

    public function __construct(EntityManagerInterface $entityManager, ListeRepository $listeRepository)
    {
        $this->entityManager = $entityManager;
        $this->listeRepository = $listeRepository;
    }

    public function importCotisations(UploadedFile $file): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'messages' => []
        ];

        try {
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

                // Récupérer les données
                $adherentNom = $this->getValue($row, 0); // Colonne A: Nom de l'adhérent
                $periode = $this->getValue($row, 1);     // Colonne B: Période (année)
                $montant = $this->getValue($row, 2);      // Colonne C: Montant
                $montantPaye = $this->getValue($row, 3);  // Colonne D: Montant payé
                $modePaiement = $this->getValue($row, 4); // Colonne E: Mode de paiement
                $reference = $this->getValue($row, 5);    // Colonne F: Référence
                $datePaiement = $this->getValue($row, 6); // Colonne G: Date de paiement
                $observation = $this->getValue($row, 7);  // Colonne H: Observation

                // Vérifier les champs obligatoires
                if (empty($adherentNom)) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - Le nom de l'adhérent est obligatoire.";
                    continue;
                }

                if (empty($periode)) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - La période est obligatoire.";
                    continue;
                }

                // Rechercher l'adhérent par nom
                $adherent = $this->listeRepository->findOneBy(['nom' => $adherentNom]);
                
                if (!$adherent) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - Adhérent '{$adherentNom}' non trouvé.";
                    continue;
                }

                try {
                    // Vérifier si une cotisation existe déjà pour cette période
                    $existingCotisation = $this->entityManager
                        ->getRepository(Cotisation::class)
                        ->findOneBy([
                            'adherent' => $adherent,
                            'periode' => $periode
                        ]);

                    if ($existingCotisation) {
                        // Mettre à jour la cotisation existante
                        $cotisation = $existingCotisation;
                        if ($montant !== null) {
                            $cotisation->setMontant((float) $montant);
                        }
                        if ($montantPaye !== null) {
                            $cotisation->setMontantPaye((float) $montantPaye);
                        }
                        if ($observation !== null) {
                            $cotisation->setObservation($observation);
                        }
                        $cotisation->updateStatut();
                    } else {
                        // Créer une nouvelle cotisation
                        $cotisation = new Cotisation();
                        $cotisation->setAdherent($adherent);
                        $cotisation->setPeriode($periode);
                        $cotisation->setMontant((float) ($montant ?? 0));
                        $cotisation->setMontantPaye((float) ($montantPaye ?? 0));
                        $cotisation->setObservation($observation);
                        $cotisation->setBaremeId(null);
                        $cotisation->setBaremeLibelle('Import Excel');
                        $cotisation->updateStatut();
                        
                        $this->entityManager->persist($cotisation);
                    }

                    // Si un paiement est mentionné, créer un paiement associé
                    if ($montantPaye !== null && (float) $montantPaye > 0) {
                        $paiement = new Paiement();
                        $paiement->setCotisation($cotisation);
                        $paiement->setMontant((float) $montantPaye);
                        $paiement->setModePaiement($modePaiement ?? 'Import Excel');
                        $paiement->setReference($reference ?? 'Import');
                        $paiement->setPeriode($periode);
                        $paiement->setCommentaire('Importé depuis Excel');
                        
                        // Date de paiement
                        if ($datePaiement) {
                            $date = \DateTime::createFromFormat('d/m/Y', $datePaiement);
                            if ($date) {
                                $paiement->setDatePaiement($date);
                            } else {
                                $paiement->setDatePaiement(new \DateTime());
                            }
                        } else {
                            $paiement->setDatePaiement(new \DateTime());
                        }
                        
                        $this->entityManager->persist($paiement);
                    }

                    $this->entityManager->flush();
                    
                    $results['success']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Cotisation pour '{$adherentNom}' ({$periode}) importée avec succès.";

                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['messages'][] = "Ligne " . ($rowIndex + 2) . " : Erreur - " . $e->getMessage();
                }
            }

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