<?php

namespace App\Controller;

use App\Repository\SebtpRepository;
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

#[Route('/export/sebtp')]
class ExportSebtpController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_export_sebtp_form', methods: ['GET'])]
    public function form(SebtpRepository $sebtpRepo): Response
    {
        $instances = $sebtpRepo->getDistinctInstances();
        $mandats = $sebtpRepo->getDistinctMandats();
        
        return $this->render('export/sebtp.html.twig', [
            'instances' => $instances,
            'mandats' => $mandats,
        ]);
    }

    #[Route('/export-excel', name: 'app_export_sebtp_excel', methods: ['GET'])]
    public function exportExcel(Request $request, SebtpRepository $sebtpRepo): Response
    {
        $instance = $request->query->get('instance');
        $mandat = $request->query->get('mandat');
        $search = $request->query->get('search');

        $sebtps = $sebtpRepo->getForExport($instance, $mandat, $search);

        // Audit log
        $this->auditLogger->logExport(
            'Sebtp',
            sprintf(
                'Export Excel - Instance: %s, Mandat: %s, Recherche: %s, Nb lignes: %d',
                $instance ?: 'toutes',
                $mandat ?: 'tous',
                $search ?: 'aucune',
                count($sebtps)
            )
        );

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'SEBTP - Liste des organismes');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Instance: ' . ($instance ?: 'Toutes'));
        $sheet->setCellValue('D' . $row, 'Mandat: ' . ($mandat ?: 'Tous'));
        $sheet->setCellValue('F' . $row, 'Recherche: ' . ($search ?: 'Aucune'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row = 5;
        $headers = [
            'A' => 'N°',
            'B' => 'Instance',
            'C' => 'Organisme',
            'D' => 'Mandat',
            'E' => 'Représentant',
            'F' => 'Observation',
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

        $row = 6;
        $numero = 1;
        foreach ($sebtps as $sebtp) {
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, $sebtp->getInstance());
            $sheet->setCellValue('C' . $row, $sebtp->getNomOrganisme());
            $sheet->setCellValue('D' . $row, $sebtp->getMandat() ?? '-');
            $sheet->setCellValue('E' . $row, $sebtp->getNomRepresentant() ?? '-');
            $sheet->setCellValue('F' . $row, $sebtp->getObservation() ?? '-');
            $sheet->setCellValue('G' . $row, $sebtp->getId());
            
            $row++;
            $numero++;
        }

        if ($row > 6) {
            $sheet->setCellValue('A' . $row, 'TOTAL:');
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('C' . $row, ($numero - 1) . ' organisme(s)');
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        foreach (range('A', 'G') as $col) {
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
        $sheet->getStyle('A5:G' . ($row))->applyFromArray($styleArray);

        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('sebtp_organismes_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }
}