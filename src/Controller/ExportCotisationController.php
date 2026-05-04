<?php

namespace App\Controller;

use App\Repository\ListeRepository;
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
use Symfony\Component\Routing\Attribute\Route;

#[Route('/export')]
final class ExportCotisationController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_export_cotisations_form', methods: ['GET'])]
    public function form(): Response
    {
        return $this->render('export/cotisations.html.twig');
    }

    #[Route('/cotisations', name: 'app_export_cotisations', methods: ['GET'])]
    public function exportCotisations(Request $request, ListeRepository $listeRepo): Response
    {
        $annee = $request->query->get('annee');
        $statut = $request->query->get('statut', 'tous');
        $statutAdherent = $request->query->get('statutAdherent', 'tous');

        $cotisations = $listeRepo->getCotisationsForExport($annee, $statut, $statutAdherent);

        // Audit log
        $this->auditLogger->logExport(
            'Cotisation',
            sprintf(
                'Export Excel - Année: %s, Statut cotisation: %s, Statut adhérent: %s, Nb lignes: %d',
                $annee ?: 'toutes',
                $statut,
                $statutAdherent,
                count($cotisations)
            )
        );

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Titre du document
        $sheet->setCellValue('A1', 'SEBTP - État des cotisations');
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
        $sheet->setCellValue('D' . $row, 'Statut cotisation: ' . ($statut === 'tous' ? 'Tous' : ($statut === 'paye' ? 'Payé' : 'Impayé')));
        $sheet->setCellValue('F' . $row, 'Statut adhérent: ' . ($statutAdherent === 'tous' ? 'Tous' : $statutAdherent));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        // En-têtes du tableau
        $row = 5;
        $headers = [
            'A' => 'N°',
            'B' => 'Adhérent',
            'C' => 'Email',
            'D' => 'Téléphone',
            'E' => 'Adresse',
            'F' => 'Année',
            'G' => 'Montant (MGA)',
            'H' => 'Référence',
            'I' => 'Statut',
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

        // Données
        $row = 6;
        $numero = 1;
        foreach ($cotisations as $cotisation) {
            $statutValue = $cotisation['cotisation_statut'] ?? '';
            $statutLibelle = $this->getStatutLibelle($statutValue);
            $montant = (float) ($cotisation['cotisation_montant'] ?? 0);
            
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, $cotisation['adherent_nom']);
            $sheet->setCellValue('C' . $row, $cotisation['adherent_email']);
            $sheet->setCellValue('D' . $row, $cotisation['adherent_telephone']);
            $sheet->setCellValue('E' . $row, $cotisation['adherent_adresse']);
            $sheet->setCellValue('F' . $row, $cotisation['cotisation_periode'] ?? '-');
            $sheet->setCellValue('G' . $row, $montant);
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0');
            
            $sheet->setCellValue('H' . $row, $cotisation['cotisation_reference'] ?? '-');
            $sheet->setCellValue('I' . $row, $statutLibelle);
            $sheet->setCellValue('J' . $row, $cotisation['cotisation_observation'] ?? '-');

            $statutCell = 'I' . $row;
            if ($statutValue === 'payé' || $statutValue === 'paye') {
                $sheet->getStyle($statutCell)->getFont()->getColor()->setARGB('FF10B981');
                $sheet->getStyle($statutCell)->getFont()->setBold(true);
            } elseif ($statutValue === 'partiel') {
                $sheet->getStyle($statutCell)->getFont()->getColor()->setARGB('FFF59E0B');
                $sheet->getStyle($statutCell)->getFont()->setBold(true);
            } elseif ($statutValue === 'impaye') {
                $sheet->getStyle($statutCell)->getFont()->getColor()->setARGB('FFEF4444');
                $sheet->getStyle($statutCell)->getFont()->setBold(true);
            }

            $row++;
            $numero++;
        }

        if ($row > 6) {
            $sheet->setCellValue('F' . $row, 'TOTAL:');
            $sheet->getStyle('F' . $row)->getFont()->setBold(true);
            $sheet->setCellValue('G' . $row, '=SUM(G6:G' . ($row - 1) . ')');
            $sheet->getStyle('G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('G' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0');
        }

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
        $sheet->getStyle('A5:J' . ($row))->applyFromArray($styleArray);

        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('cotisations_%s_%s.xlsx', date('Ymd'), date('His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    private function getStatutLibelle(string $statut): string
    {
        return match ($statut) {
            'payé', 'paye' => 'Payé',
            'partiel' => 'Partiel',
            'impaye' => 'Impayé',
            default => 'Non renseigné',
        };
    }
}