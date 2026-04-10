<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use App\Repository\ParticipationRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/export/evenement')]
class ExportEvenementController extends AbstractController
{
    #[Route('/', name: 'app_export_evenement_form', methods: ['GET'])]
    public function form(EvenementRepository $evenementRepo): Response
    {
        $years = $evenementRepo->getAvailableYears();
        
        return $this->render('export/evenement.html.twig', [
            'years' => $years,
        ]);
    }

    #[Route('/export-excel', name: 'app_export_evenement_excel', methods: ['GET'])]
    public function exportExcel(Request $request, EvenementRepository $evenementRepo): Response
    {
        $annee = $request->query->get('annee');
        $search = $request->query->get('search');

        $evenements = $evenementRepo->getForExport($annee, $search);

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Titre du document
        $sheet->setCellValue('A1', 'SEBTP - Liste des événements');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Date d'export
        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Filtres appliqués
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Année: ' . ($annee ?: 'Toutes'));
        $sheet->setCellValue('D' . $row, 'Recherche: ' . ($search ?: 'Aucune'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        // En-têtes du tableau
        $row = 5;
        $headers = [
            'A' => 'N°',
            'B' => 'Nom',
            'C' => 'Date',
            'D' => 'Montant (MGA)',
            'E' => 'Commentaire',
            'F' => 'Nb participants',
            'G' => 'ID'
        ];

        foreach ($headers as $col => $value) {
            $sheet->setCellValue($col . $row, $value);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4F46E5');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Données
        $row = 6;
        $numero = 1;
        foreach ($evenements as $evenement) {
            $nbParticipants = $evenement->getParticipations()->count();
            
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, $evenement->getNom());
            $sheet->setCellValue('C' . $row, $evenement->getDate() ? $evenement->getDate()->format('d/m/Y') : 'Date non définie');
            $sheet->setCellValue('D' . $row, $evenement->getMontant() ? number_format($evenement->getMontant(), 0, '.', ' ') : '0');
            $sheet->setCellValue('E' . $row, $evenement->getCommentaire() ?? '-');
            $sheet->setCellValue('F' . $row, $nbParticipants);
            $sheet->setCellValue('G' . $row, $evenement->getId());
            
            // Colorer selon le nombre de participants
            if ($nbParticipants == 0) {
                $sheet->getStyle('F' . $row)->getFont()->getColor()->setARGB('FFEF4444');
            } elseif ($nbParticipants > 10) {
                $sheet->getStyle('F' . $row)->getFont()->getColor()->setARGB('FF10B981');
            }
            
            $row++;
            $numero++;
        }

        // Ajouter une ligne de total
        if ($row > 6) {
            $sheet->setCellValue('A' . $row, 'TOTAL:');
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('C' . $row, ($numero - 1) . ' événement(s)');
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        // Ajuster la largeur des colonnes
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Ajouter des bordures au tableau
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ];
        $sheet->getStyle('A5:G' . ($row))->applyFromArray($styleArray);

        // Création du fichier
        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('evenements_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/export-detail-excel', name: 'app_export_evenement_detail_excel', methods: ['GET'])]
    public function exportDetailExcel(Request $request, EvenementRepository $evenementRepo): Response
    {
        $annee = $request->query->get('annee');
        $search = $request->query->get('search');

        $evenements = $evenementRepo->getForExport($annee, $search);

        // Création du fichier Excel détaillé
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Titre du document
        $sheet->setCellValue('A1', 'SEBTP - Détail des événements avec participants');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Date d'export
        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        // Filtres appliqués
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Année: ' . ($annee ?: 'Toutes'));
        $sheet->setCellValue('D' . $row, 'Recherche: ' . ($search ?: 'Aucune'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        // En-têtes du tableau détaillé
        $row = 5;
        $headers = [
            'A' => 'N° événement',
            'B' => 'Événement',
            'C' => 'Date',
            'D' => 'Montant (MGA)',
            'E' => 'Participant',
            'F' => 'Email participant',
            'G' => 'Téléphone participant',
            'H' => 'Statut paiement',
            'I' => 'Statut adhérent',
            'J' => 'Observation'
        ];

        foreach ($headers as $col => $value) {
            $sheet->setCellValue($col . $row, $value);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4F46E5');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Données détaillées
        $row = 6;
        foreach ($evenements as $evenement) {
            $participations = $evenement->getParticipations();
            
            if ($participations->count() > 0) {
                foreach ($participations as $participation) {
                    $adherent = $participation->getAdherent();
                    $sheet->setCellValue('A' . $row, $evenement->getId());
                    $sheet->setCellValue('B' . $row, $evenement->getNom());
                    $sheet->setCellValue('C' . $row, $evenement->getDate() ? $evenement->getDate()->format('d/m/Y') : 'Date non définie');
                    $sheet->setCellValue('D' . $row, $evenement->getMontant() ? number_format($evenement->getMontant(), 0, '.', ' ') : '0');
                    $sheet->setCellValue('E' . $row, $adherent ? $adherent->getNom() : '-');
                    $sheet->setCellValue('F' . $row, $adherent ? $adherent->getEmail() : '-');
                    $sheet->setCellValue('G' . $row, $adherent ? $adherent->getNumero() : '-');
                    
                    // Statut paiement avec couleur
                    $statutPaiement = $participation->getStatutPaiement();
                    $sheet->setCellValue('H' . $row, $statutPaiement === 'paye' ? 'Payé' : 'Impayé');
                    if ($statutPaiement === 'paye') {
                        $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('FF10B981');
                    } else {
                        $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('FFEF4444');
                    }
                    
                    $sheet->setCellValue('I' . $row, $adherent ? ($adherent->getStatut() ?? '-') : '-');
                    $sheet->setCellValue('J' . $row, $evenement->getCommentaire() ?? '-');
                    
                    $row++;
                }
            } else {
                // Événement sans participant
                $sheet->setCellValue('A' . $row, $evenement->getId());
                $sheet->setCellValue('B' . $row, $evenement->getNom());
                $sheet->setCellValue('C' . $row, $evenement->getDate() ? $evenement->getDate()->format('d/m/Y') : 'Date non définie');
                $sheet->setCellValue('D' . $row, $evenement->getMontant() ? number_format($evenement->getMontant(), 0, '.', ' ') : '0');
                $sheet->setCellValue('E' . $row, 'Aucun participant');
                $sheet->setCellValue('F' . $row, '-');
                $sheet->setCellValue('G' . $row, '-');
                $sheet->setCellValue('H' . $row, '-');
                $sheet->setCellValue('I' . $row, '-');
                $sheet->setCellValue('J' . $row, $evenement->getCommentaire() ?? '-');
                $row++;
            }
        }

        // Ajuster la largeur des colonnes
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Ajouter des bordures au tableau
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ];
        $sheet->getStyle('A5:J' . ($row - 1))->applyFromArray($styleArray);

        // Création du fichier
        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('evenements_detail_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
