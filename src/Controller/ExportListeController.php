<?php

namespace App\Controller;

use App\Entity\Liste;
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
use Symfony\Component\Routing\Annotation\Route;

#[Route('/export/liste')]
class ExportListeController extends AbstractController
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    #[Route('/', name: 'app_export_liste_form', methods: ['GET'])]
    public function form(ListeRepository $listeRepo): Response
    {
        $statuts = $listeRepo->getDistinctStatuts();
        $statutsMembres = $listeRepo->getDistinctStatutsMembres();
        $filieres = $listeRepo->getDistinctFilieres();
        $activites = $listeRepo->getDistinctActivites();
        
        return $this->render('export/liste.html.twig', [
            'statuts' => $statuts,
            'statutsMembres' => $statutsMembres,
            'filieres' => $filieres,
            'activites' => $activites,
        ]);
    }

    #[Route('/export-excel', name: 'app_export_liste_excel', methods: ['GET'])]
    public function exportExcel(Request $request, ListeRepository $listeRepo): Response
    {
        $statut = $request->query->get('statut');
        $statutMenmbre = $request->query->get('statutMenmbre');
        $filiere = $request->query->get('filiere');
        $activite = $request->query->get('activite');
        $search = $request->query->get('search');

        $adherents = $listeRepo->getForExport($statut, $statutMenmbre, $filiere, $activite, $search);

        // Audit log
        $this->auditLogger->logExport(
            'Liste',
            sprintf(
                'Export Excel - Statut: %s, Statut membre: %s, Filière: %s, Activité: %s, Recherche: %s, Nb lignes: %d',
                $statut ?: 'tous',
                $statutMenmbre ?: 'tous',
                $filiere ?: 'toutes',
                $activite ?: 'toutes',
                $search ?: 'aucune',
                count($adherents)
            )
        );

        // Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'SEBTP - Liste des adhérents');
        $sheet->mergeCells('A1:V1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Date d\'export : ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:V2');
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $row = 3;
        $sheet->setCellValue('A' . $row, 'Filtres :');
        $sheet->setCellValue('B' . $row, 'Statut: ' . ($statut ?: 'Tous'));
        $sheet->setCellValue('D' . $row, 'Statut membre: ' . ($statutMenmbre ?: 'Tous'));
        $sheet->setCellValue('F' . $row, 'Filière: ' . ($filiere ?: 'Toutes'));
        $sheet->setCellValue('H' . $row, 'Activité: ' . ($activite ?: 'Toutes'));
        $sheet->setCellValue('J' . $row, 'Recherche: ' . ($search ?: 'Aucune'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        $row = 5;
        $headers = [
            'A' => 'N°',
            'B' => 'Nom',
            'C' => 'Email',
            'D' => 'Téléphone',
            'E' => 'Adresse',
            'F' => 'Site web',
            'G' => 'Activité',
            'H' => 'Filière',
            'I' => 'Nb employés',
            'J' => 'Cotisation FMTP',
            'K' => 'DG',
            'L' => 'Tél. DG',
            'M' => 'Statut',
            'N' => 'Statut membre',
            'O' => 'Fonction SEBTP',
            'P' => 'Mandat',
            'Q' => 'Contact RH - Nom',
            'R' => 'Contact RH - Email',
            'S' => 'Contact RH - Téléphone',
            'T' => 'Contact Compta - Nom',
            'U' => 'Contact Compta - Email',
            'V' => 'Contact Compta - Téléphone'
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
        foreach ($adherents as $adherent) {
            // Récupérer les contacts par fonction
            $contactRH = $this->getContactByFonction($adherent, 'RH');
            $contactCompta = $this->getContactByFonction($adherent, 'Compta');
            
            // Données contact RH
            $rhNom = $contactRH ? $contactRH->getNom() : '-';
            $rhEmail = $contactRH ? $contactRH->getEmail() : '-';
            $rhTelephone = $contactRH ? $contactRH->getTelephone() : '-';
            
            // Données contact Compta
            $comptaNom = $contactCompta ? $contactCompta->getNom() : '-';
            $comptaEmail = $contactCompta ? $contactCompta->getEmail() : '-';
            $comptaTelephone = $contactCompta ? $contactCompta->getTelephone() : '-';
            
            $sheet->setCellValue('A' . $row, $numero);
            $sheet->setCellValue('B' . $row, $adherent->getNom());
            $sheet->setCellValue('C' . $row, $adherent->getEmail() ?? '-');
            $sheet->setCellValue('D' . $row, $adherent->getNumero() ?? '-');
            $sheet->setCellValue('E' . $row, $adherent->getAdresse() ?? '-');
            $sheet->setCellValue('F' . $row, $adherent->getSiteWeb() ?? '-');
            $sheet->setCellValue('G' . $row, $adherent->getActivite());
            $sheet->setCellValue('I' . $row, $adherent->getNbEmployes() ?? '-');
            $filiere = $adherent->getFiliere();
                if (is_array($filiere)) {
                    $filiereString = implode(', ', $filiere);
                } else {
                    $filiereString = $filiere ?? '-';
                }
                $sheet->setCellValue('H' . $row, $filiereString);
            $sheet->setCellValue('J' . $row, $adherent->getCotFMTP() ?? '-');
            $sheet->setCellValue('K' . $row, $adherent->getDg());
            $sheet->setCellValue('L' . $row, $adherent->getTelephoneDg() ?? '-');
            $sheet->setCellValue('M' . $row, $adherent->getStatut() ?? '-');
            $sheet->setCellValue('N' . $row, $adherent->getStatutMenmbre() ?? '-');
            $sheet->setCellValue('O' . $row, $adherent->getFonctionSEBTP() ?? '-');
            $sheet->setCellValue('P' . $row, $adherent->getMandat() ?? '-');
            $sheet->setCellValue('Q' . $row, $rhNom);
            $sheet->setCellValue('R' . $row, $rhEmail);
            $sheet->setCellValue('S' . $row, $rhTelephone);
            $sheet->setCellValue('T' . $row, $comptaNom);
            $sheet->setCellValue('U' . $row, $comptaEmail);
            $sheet->setCellValue('V' . $row, $comptaTelephone);

            // Colorer la ligne selon le statut
            $statutValue = $adherent->getStatut();
            if ($statutValue === 'actif') {
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setARGB('FF10B981');
                $sheet->getStyle('M' . $row)->getFont()->setBold(true);
            } elseif ($statutValue === 'inactif') {
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setARGB('FFF59E0B');
                $sheet->getStyle('M' . $row)->getFont()->setBold(true);
            } elseif ($statutValue === 'radie') {
                $sheet->getStyle('M' . $row)->getFont()->getColor()->setARGB('FFEF4444');
                $sheet->getStyle('M' . $row)->getFont()->setBold(true);
            }

            $row++;
            $numero++;
        }

        if ($row > 6) {
            $sheet->setCellValue('A' . $row, 'TOTAL:');
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('C' . $row, ($numero - 1) . ' adhérent(s)');
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        foreach (range('A', 'V') as $col) {
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
        $sheet->getStyle('A5:V' . ($row))->applyFromArray($styleArray);

        $writer = new Xlsx($spreadsheet);
        $fileName = sprintf('adherents_%s.xlsx', date('Ymd_His'));
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    /**
     * Récupère le contact d'un adhérent par fonction
     */
    private function getContactByFonction(Liste $adherent, string $fonction): ?\App\Entity\Contact
    {
        foreach ($adherent->getContacts() as $contact) {
            if ($contact->getFonction() === $fonction) {
                return $contact;
            }
        }
        return null;
    }
}