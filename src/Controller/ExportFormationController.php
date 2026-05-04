<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Repository\FormationRepository;
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

#[Route('/export/formation')]
class ExportFormationController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_export_formation_form', methods: ['GET'])]
    public function form(FormationRepository $formationRepo): Response
    {
        $years = $formationRepo->getAvailableYears();
        $formations = $formationRepo->getAllForSelect();
        
        return $this->render('export/formation.html.twig', [
            'years' => $years,
            'formations' => $formations,
        ]);
    }

    #[Route('/export-excel', name: 'app_export_formation_excel', methods: ['GET'])]
    public function exportExcel(Request $request, FormationRepository $formationRepo): Response
    {
        $annee = $request->query->get('annee');
        $formationId = $request->query->get('formation_id');
        
        if (empty($formationId) || !is_numeric($formationId)) {
            $formationId = null;
        } else {
            $formationId = (int) $formationId;
        }

        $formations = $formationRepo->getForExportWithFormationId($annee, $formationId);

        // Audit log
        $this->auditLogger->logExport(
            'Formation',
            sprintf(
                'Export Excel - Année: %s, Formation ID: %s, Nb formations: %d',
                $annee ?: 'toutes',
                $formationId ?: 'tous',
                count($formations)
            )
        );

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'SEBTP - Liste des formations');
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Année: ' . ($annee ?: 'Toutes'));
        
        $formationNom = '';
        if ($formationId) {
            $formation = $formationRepo->find($formationId);
            $formationNom = $formation ? $formation->getNom() : 'Non trouvé';
        }
        $sheet->setCellValue('D' . $row, 'Formation: ' . ($formationNom ?: 'Toutes'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row = 5;
        $headers = [
            'A' => 'N°',
            'B' => 'Nom',
            'C' => 'Référence',
            'D' => 'Type',
            'E' => 'Date début',
            'F' => 'Date fin',
            'G' => 'Organisateur',
            'H' => 'Nb participants',
            'I' => 'Participants',
            'J' => 'Agents participants',
            'K' => 'Remarque'
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

        $totalFormations = 0;
        $totalParticipants = 0;
        
        $row = 6;
        $numero = 1;
        foreach ($formations as $formation) {
            $nbParticipants = $formation->getParticipants()->count();
            $participantsList = $this->getParticipantsList($formation);
            $agentsList = $this->getAgentsList($formation);
            
            $totalFormations++;
            $totalParticipants += $nbParticipants;
            
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, $formation->getNom());
            $sheet->setCellValue('C' . $row, $formation->getReference() ?? '-');
            $sheet->setCellValue('D' . $row, $formation->getType());
            $sheet->setCellValue('E' . $row, $formation->getDateDebut() ? $formation->getDateDebut()->format('d/m/Y') : '-');
            $sheet->setCellValue('F' . $row, $formation->getDateFin() ? $formation->getDateFin()->format('d/m/Y') : '-');
            $sheet->setCellValue('G' . $row, $formation->getOrganisateur());
            $sheet->setCellValue('H' . $row, $nbParticipants);
            $sheet->setCellValue('I' . $row, $participantsList);
            $sheet->getStyle('I' . $row)->getAlignment()->setWrapText(true);
            $sheet->setCellValue('J' . $row, $agentsList);
            $sheet->getStyle('J' . $row)->getAlignment()->setWrapText(true);
            $sheet->setCellValue('K' . $row, $formation->getRemarque() ?? '-');
            
            if ($nbParticipants == 0) {
                $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('FFEF4444');
            } elseif ($nbParticipants > 5) {
                $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB('FF10B981');
            }
            
            $row++;
            $numero++;
        }

        if ($row > 6) {
            $sheet->setCellValue('A' . $row, 'TOTAUX:');
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            
            $sheet->setCellValue('C' . $row, $totalFormations . ' formation(s)');
            $sheet->getStyle('C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('C' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            
            $sheet->setCellValue('H' . $row, $totalParticipants);
            $sheet->getStyle('H' . $row)->getFont()->setBold(true);
            $sheet->getStyle('H' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        foreach (range('A', 'K') as $col) {
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
        $sheet->getStyle('A5:K' . ($row))->applyFromArray($styleArray);

        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('formations_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/export-detail-excel', name: 'app_export_formation_detail_excel', methods: ['GET'])]
    public function exportDetailExcel(Request $request, FormationRepository $formationRepo): Response
    {
        $annee = $request->query->get('annee');
        $formationId = $request->query->get('formation_id');
        
        if (empty($formationId) || !is_numeric($formationId)) {
            $formationId = null;
        } else {
            $formationId = (int) $formationId;
        }

        $formations = $formationRepo->getForExportWithFormationId($annee, $formationId);

        // Audit log
        $this->auditLogger->logExport(
            'FormationDetail',
            sprintf(
                'Export Excel détaillé - Année: %s, Formation ID: %s, Nb formations: %d',
                $annee ?: 'toutes',
                $formationId ?: 'tous',
                count($formations)
            )
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'SEBTP - Détail des formations avec participants');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Année: ' . ($annee ?: 'Toutes'));
        
        $formationNom = '';
        if ($formationId) {
            $formation = $formationRepo->find($formationId);
            $formationNom = $formation ? $formation->getNom() : 'Non trouvé';
        }
        $sheet->setCellValue('D' . $row, 'Formation: ' . ($formationNom ?: 'Toutes'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row = 5;
        $headers = [
            'A' => 'N° formation',
            'B' => 'Formation',
            'C' => 'Date début',
            'D' => 'Date fin',
            'E' => 'Participant (entreprise)',
            'F' => 'Contact entreprise',
            'G' => 'Agents / Représentants',
            'H' => 'Remarque'
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
        $totalFormations = 0;
        
        $row = 6;
        $formationsTraitees = [];
        
        foreach ($formations as $formation) {
            $participants = $formation->getParticipants();
            $participantsDetails = $formation->getParticipantsDetails() ?? [];
            
            if ($participants->count() > 0) {
                foreach ($participants as $participant) {
                    $detail = $participantsDetails[$participant->getId()] ?? '';
                    
                    $sheet->setCellValue('A' . $row, $formation->getId());
                    $sheet->setCellValue('B' . $row, $formation->getNom());
                    $sheet->setCellValue('C' . $row, $formation->getDateDebut() ? $formation->getDateDebut()->format('d/m/Y') : '-');
                    $sheet->setCellValue('D' . $row, $formation->getDateFin() ? $formation->getDateFin()->format('d/m/Y') : '-');
                    $sheet->setCellValue('E' . $row, $participant->getNom());
                    $sheet->setCellValue('F' . $row, $participant->getEmail() . ' / ' . $participant->getNumero());
                    $sheet->setCellValue('G' . $row, $detail ?: 'Non spécifié');
                    $sheet->setCellValue('H' . $row, $formation->getRemarque() ?? '-');
                    
                    $totalParticipants++;
                    $row++;
                }
            } else {
                $sheet->setCellValue('A' . $row, $formation->getId());
                $sheet->setCellValue('B' . $row, $formation->getNom());
                $sheet->setCellValue('C' . $row, $formation->getDateDebut() ? $formation->getDateDebut()->format('d/m/Y') : '-');
                $sheet->setCellValue('D' . $row, $formation->getDateFin() ? $formation->getDateFin()->format('d/m/Y') : '-');
                $sheet->setCellValue('E' . $row, 'Aucun participant');
                $sheet->setCellValue('F' . $row, '-');
                $sheet->setCellValue('G' . $row, '-');
                $sheet->setCellValue('H' . $row, $formation->getRemarque() ?? '-');
                $row++;
            }
            $formationsTraitees[$formation->getId()] = true;
        }

        $sheet->setCellValue('A' . $row, 'TOTAUX:');
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        
        $sheet->setCellValue('C' . $row, count($formationsTraitees) . ' formation(s)');
        $sheet->getStyle('C' . $row)->getFont()->setBold(true);
        $sheet->getStyle('C' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        
        $sheet->setCellValue('E' . $row, $totalParticipants . ' participant(s)');
        $sheet->getStyle('E' . $row)->getFont()->setBold(true);
        $sheet->getStyle('E' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');

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
        $fileName = sprintf('formations_detail_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    private function getParticipantsList(Formation $formation): string
    {
        $participants = [];
        foreach ($formation->getParticipants() as $participant) {
            $participants[] = $participant->getNom();
        }
        return implode(', ', $participants);
    }

    private function getAgentsList(Formation $formation): string
    {
        $details = $formation->getParticipantsDetails() ?? [];
        $agents = [];
        
        foreach ($details as $detail) {
            if (!empty($detail)) {
                $agents[] = $detail;
            }
        }
        
        return implode(' | ', $agents);
    }
}