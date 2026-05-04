<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use App\Service\AuditLogger;
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
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_export_evenement_form', methods: ['GET'])]
    public function form(EvenementRepository $evenementRepo): Response
    {
        $years = $evenementRepo->getAvailableYears();
        $evenements = $evenementRepo->getAllForSelect();
        
        return $this->render('export/evenement.html.twig', [
            'years' => $years,
            'evenements' => $evenements,
        ]);
    }

    #[Route('/export-excel', name: 'app_export_evenement_excel', methods: ['GET'])]
    public function exportExcel(Request $request, EvenementRepository $evenementRepo): Response
    {
        $annee = $request->query->get('annee');
        $evenementId = $request->query->get('evenement_id');
        
        if (empty($evenementId) || !is_numeric($evenementId)) {
            $evenementId = null;
        } else {
            $evenementId = (int) $evenementId;
        }

        $evenements = $evenementRepo->getForExportWithEventId($annee, $evenementId);

        // Audit log
        $this->auditLogger->logExport(
            'Evenement',
            sprintf(
                'Export Excel - Année: %s, Événement ID: %s, Nb événements: %d',
                $annee ?: 'toutes',
                $evenementId ?: 'tous',
                count($evenements)
            )
        );

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'SEBTP - Liste des événements');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Année: ' . ($annee ?: 'Toutes'));
        
        $evenementNom = '';
        if ($evenementId) {
            $evenement = $evenementRepo->find($evenementId);
            $evenementNom = $evenement ? $evenement->getNom() : 'Non trouvé';
        }
        $sheet->setCellValue('D' . $row, 'Événement: ' . ($evenementNom ?: 'Tous'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row = 5;
        $headers = [
            'A' => 'N°',
            'B' => 'Nom',
            'C' => 'Date',
            'D' => 'Montant unitaire (MGA)',
            'E' => 'Montant total (MGA)',
            'F' => 'Commentaire',
            'G' => 'Nb participants',
            'H' => 'ID'
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

        $totalMontantUnitaire = 0;
        $totalMontantGlobal = 0;
        $totalParticipants = 0;
        
        $row = 6;
        $numero = 1;
        foreach ($evenements as $evenement) {
            $nbParticipants = $evenement->getParticipations()->count();
            $montantUnitaire = (float) ($evenement->getMontant() ?? 0);
            $montantTotal = $montantUnitaire * $nbParticipants;
            
            $totalMontantUnitaire += $montantUnitaire;
            $totalMontantGlobal += $montantTotal;
            $totalParticipants += $nbParticipants;
            
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, $evenement->getNom());
            $sheet->setCellValue('C' . $row, $evenement->getDate() ? $evenement->getDate()->format('d/m/Y') : 'Date non définie');
            $sheet->setCellValue('D' . $row, $montantUnitaire);
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->setCellValue('E' . $row, $montantTotal);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->setCellValue('F' . $row, $evenement->getCommentaire() ?? '-');
            $sheet->setCellValue('G' . $row, $nbParticipants);
            if ($nbParticipants == 0) {
                $sheet->getStyle('G' . $row)->getFont()->getColor()->setARGB('FFEF4444');
            } elseif ($nbParticipants > 10) {
                $sheet->getStyle('G' . $row)->getFont()->getColor()->setARGB('FF10B981');
            }
            $sheet->setCellValue('H' . $row, $evenement->getId());
            
            $row++;
            $numero++;
        }

        if ($row > 6) {
            $sheet->setCellValue('A' . $row, 'TOTAUX:');
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            
            $sheet->setCellValue('D' . $row, $totalMontantUnitaire);
            $sheet->getStyle('D' . $row)->getFont()->setBold(true);
            $sheet->getStyle('D' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
            
            $sheet->setCellValue('E' . $row, $totalMontantGlobal);
            $sheet->getStyle('E' . $row)->getFont()->setBold(true);
            $sheet->getStyle('E' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
            
            $sheet->setCellValue('G' . $row, $totalParticipants);
            $sheet->getStyle('G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('G' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ];
        $sheet->getStyle('A5:H' . ($row))->applyFromArray($styleArray);

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
        $evenementId = $request->query->get('evenement_id');
        
        if (empty($evenementId) || !is_numeric($evenementId)) {
            $evenementId = null;
        } else {
            $evenementId = (int) $evenementId;
        }

        $evenements = $evenementRepo->getForExportWithEventId($annee, $evenementId);

        // Audit log
        $this->auditLogger->logExport(
            'EvenementDetail',
            sprintf(
                'Export Excel détaillé - Année: %s, Événement ID: %s, Nb événements: %d',
                $annee ?: 'toutes',
                $evenementId ?: 'tous',
                count($evenements)
            )
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'SEBTP - Détail des événements avec participants');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Année: ' . ($annee ?: 'Toutes'));
        
        $evenementNom = '';
        if ($evenementId) {
            $evenement = $evenementRepo->find($evenementId);
            $evenementNom = $evenement ? $evenement->getNom() : 'Non trouvé';
        }
        $sheet->setCellValue('D' . $row, 'Événement: ' . ($evenementNom ?: 'Tous'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row = 5;
        $headers = [
            'A' => 'N° événement',
            'B' => 'Événement',
            'C' => 'Date',
            'D' => 'Montant unitaire (MGA)',
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

        $totalParticipants = 0;
        $totalPaye = 0;
        $totalImpaye = 0;
        $totalMontantGlobal = 0;
        
        $row = 6;
        
        foreach ($evenements as $evenement) {
            $participations = $evenement->getParticipations();
            $montantUnitaire = (float) ($evenement->getMontant() ?? 0);
            
            if ($participations->count() > 0) {
                foreach ($participations as $participation) {
                    $adherent = $participation->getAdherent();
                    $statutPaiement = $participation->getStatutPaiement();
                    
                    $sheet->setCellValue('A' . $row, $evenement->getId());
                    $sheet->setCellValue('B' . $row, $evenement->getNom());
                    $sheet->setCellValue('C' . $row, $evenement->getDate() ? $evenement->getDate()->format('d/m/Y') : 'Date non définie');
                    $sheet->setCellValue('D' . $row, $montantUnitaire);
                    $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->setCellValue('E' . $row, $adherent ? $adherent->getNom() : '-');
                    $sheet->setCellValue('F' . $row, $adherent ? $adherent->getEmail() : '-');
                    $sheet->setCellValue('G' . $row, $adherent ? $adherent->getNumero() : '-');
                    
                    $statutLibelle = $statutPaiement === 'paye' ? 'Payé' : 'Impayé';
                    $sheet->setCellValue('H' . $row, $statutLibelle);
                    
                    if ($statutPaiement === 'paye') {
                        $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('FF10B981');
                        $totalPaye++;
                        $totalMontantGlobal += $montantUnitaire;
                    } else {
                        $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('FFEF4444');
                        $totalImpaye++;
                    }
                    
                    $sheet->setCellValue('I' . $row, $adherent ? ($adherent->getStatut() ?? '-') : '-');
                    $sheet->setCellValue('J' . $row, $evenement->getCommentaire() ?? '-');
                    
                    $totalParticipants++;
                    $row++;
                }
            } else {
                $sheet->setCellValue('A' . $row, $evenement->getId());
                $sheet->setCellValue('B' . $row, $evenement->getNom());
                $sheet->setCellValue('C' . $row, $evenement->getDate() ? $evenement->getDate()->format('d/m/Y') : 'Date non définie');
                $sheet->setCellValue('D' . $row, $montantUnitaire);
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->setCellValue('E' . $row, 'Aucun participant');
                $sheet->setCellValue('F' . $row, '-');
                $sheet->setCellValue('G' . $row, '-');
                $sheet->setCellValue('H' . $row, '-');
                $sheet->setCellValue('I' . $row, '-');
                $sheet->setCellValue('J' . $row, $evenement->getCommentaire() ?? '-');
                $row++;
            }
        }

        $sheet->setCellValue('A' . $row, 'TOTAUX:');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        
        $sheet->setCellValue('D' . $row, $totalMontantGlobal);
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);
        $sheet->getStyle('D' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        
        $sheet->setCellValue('H' . $row, $totalParticipants);
        $sheet->getStyle('H' . $row)->getFont()->setBold(true);
        $sheet->getStyle('H' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Détail des paiements:');
        $sheet->setCellValue('B' . $row, 'Payés: ' . $totalPaye);
        $sheet->setCellValue('C' . $row, 'Impayés: ' . $totalImpaye);
        $sheet->setCellValue('D' . $row, 'Total participations: ' . $totalParticipants);
        $sheet->mergeCells('A' . $row . ':D' . $row);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Nombre total d\'événements: ' . count($evenements));
        $sheet->mergeCells('A' . $row . ':J' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true);

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ];
        $sheet->getStyle('A5:J' . ($row - 2))->applyFromArray($styleArray);

        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('evenements_detail_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }
}