<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Liste;
use App\Entity\Cotisation;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentGenerator
{
    private EntityManagerInterface $entityManager;
    private DocumentRepository $documentRepository;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        DocumentRepository $documentRepository
    ) {
        $this->entityManager = $entityManager;
        $this->documentRepository = $documentRepository;
        $this->projectDir = __DIR__ . '/../../';
    }

    /**
     * Génère un reçu de paiement
     */
    public function generateRecu(Cotisation $cotisation): Document
    {
        $adherent = $cotisation->getAdherent();
        $numero = $this->generateNumero('RECU');

        $document = new Document();
        $document->setType('recu');
        $document->setNumero($numero);
        $document->setAdherent($adherent);
        $document->setMontant($cotisation->getMontantPaye());
        $document->setPeriode($cotisation->getPeriode());
        $document->setReference($cotisation->getReference());

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $html = $this->renderRecuHtml($document, $cotisation);
        $pdfContent = $this->generatePdf($html);

        $fileName = sprintf('RECU_%s_%s.pdf', $numero, date('Ymd_His'));
        $path = $this->saveDocument($pdfContent, $adherent, $fileName);

        $document->setCheminFichier($path);
        $document->setNomFichier($fileName);
        $document->setContenu($html);

        $this->entityManager->flush();

        return $document;
    }

    /**
     * Génère une note de débit
     */
    public function generateNoteDebit(Liste $adherent, float $montant, string $periode, string $motif): Document
    {
        $numero = $this->generateNumero('ND');

        $document = new Document();
        $document->setType('note_debit');
        $document->setNumero($numero);
        $document->setAdherent($adherent);
        $document->setMontant($montant);
        $document->setPeriode($periode);
        $document->setReference($motif);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $html = $this->renderNoteDebitHtml($document, $motif);
        $pdfContent = $this->generatePdf($html);

        $fileName = sprintf('ND_%s_%s.pdf', $numero, date('Ymd_His'));
        $path = $this->saveDocument($pdfContent, $adherent, $fileName);

        $document->setCheminFichier($path);
        $document->setNomFichier($fileName);
        $document->setContenu($html);

        $this->entityManager->flush();

        return $document;
    }

    /**
     * Génère le numéro de document
     */
    private function generateNumero(string $type): string
    {
        $year = date('Y');
        $count = $this->documentRepository->countByYear($type, $year) + 1;
        $countStr = str_pad($count, 4, '0', STR_PAD_LEFT);
        return sprintf('%s-%s-%s', $type, $year, $countStr);
    }

    /**
     * Génère le PDF à partir du HTML
     */
    private function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Sauvegarde le PDF dans le dossier de l'adhérent
     */
    private function saveDocument(string $content, Liste $adherent, string $fileName): string
    {
        $adherentDir = $this->projectDir . '/public/uploads/documents/' . $adherent->getId();
        
        if (!is_dir($adherentDir)) {
            mkdir($adherentDir, 0777, true);
        }

        $path = $adherentDir . '/' . $fileName;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Rendu HTML du reçu
     */
    private function renderRecuHtml(Document $document, Cotisation $cotisation): string
    {
        $adherent = $document->getAdherent();
        $numero = $document->getNumero();
        $montantFormate = number_format($document->getMontant(), 0, '.', ' ');
        $montantLettres = $this->nombreEnLettres($document->getMontant());
        $dateCreation = $document->getDateCreation()->format('d/m/Y H:i');
        
        $adherentNom = $adherent->getNom() ?? 'Non renseigné';
        $adherentAdresse = $adherent->getAdresse() ?? 'Non renseignée';
        $adherentEmail = $adherent->getEmail() ?? 'Non renseigné';
        $adherentNumero = $adherent->getNumero() ?? 'Non renseigné';
        $periode = $cotisation->getPeriode() ?? 'Non définie';
        $reference = $cotisation->getReference() ?? 'N/A';
        $modePaiement = $cotisation->getModePaiement() ?? 'Non spécifié';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reçu de paiement N° {$numero}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #fff; padding: 30px; color: #1a1a2e; }
        .page { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 20px rgba(0,0,0,0.08); padding: 50px 45px; border: 1px solid #e8e8e8; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #2d5a27; padding-bottom: 25px; margin-bottom: 30px; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .logo { width: 60px; height: 60px; background: #2d5a27; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 900; }
        .header-left h1 { font-size: 22px; font-weight: 800; color: #2d5a27; letter-spacing: 1px; }
        .header-left p { font-size: 12px; color: #666; margin-top: 2px; }
        .header-right { text-align: right; }
        .header-right .title { font-size: 22px; font-weight: 800; color: #2d5a27; letter-spacing: 2px; }
        .header-right .subtitle { font-size: 12px; color: #666; margin-top: 4px; }
        .header-right .num { font-size: 14px; font-weight: 700; color: #e74c3c; margin-top: 6px; }
        .badge { display: inline-block; background: #2d5a27; color: #fff; padding: 4px 18px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 25px 0 30px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .info-item { display: flex; flex-direction: column; }
        .info-item .label { font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; font-weight: 600; }
        .info-item .value { font-size: 14px; font-weight: 600; color: #1a1a2e; margin-top: 3px; }
        .montant-box { text-align: center; padding: 30px; background: linear-gradient(135deg, #f0f7ee 0%, #e6f0e6 100%); border-radius: 12px; margin: 25px 0 30px 0; border: 2px solid #2d5a27; }
        .montant-box .label { font-size: 13px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .montant-box .montant { font-size: 38px; font-weight: 900; color: #2d5a27; margin: 8px 0 6px 0; }
        .montant-box .devise { font-size: 16px; color: #555; }
        .montant-box .lettres { font-size: 14px; color: #555; font-style: italic; margin-top: 5px; }
        .details-table { width: 100%; margin: 20px 0 25px 0; border-collapse: collapse; }
        .details-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .details-table td:first-child { font-weight: 600; color: #555; width: 35%; }
        .details-table td:last-child { font-weight: 500; }
        .footer { margin-top: 35px; padding-top: 20px; border-top: 2px solid #eee; display: flex; justify-content: space-between; align-items: end; }
        .footer .mention { font-size: 11px; color: #999; }
        .footer .signature { text-align: center; font-size: 11px; color: #666; }
        .footer .signature .line { width: 150px; border-top: 1px solid #333; margin: 25px auto 6px auto; }
        @media print { body { padding: 0; } .page { box-shadow: none; border: none; padding: 40px; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-left">
                <div class="logo">S</div>
                <div>
                    <h1>SEBTP</h1>
                    <p>Syndicat des Entreprises du BTP</p>
                </div>
            </div>
            <div class="header-right">
                <div class="title">REÇU</div>
                <div class="subtitle">De paiement</div>
                <div class="num">N° {$numero}</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="label">Adhérent</span>
                <span class="value">{$adherentNom}</span>
            </div>
            <div class="info-item">
                <span class="label">Adresse</span>
                <span class="value">{$adherentAdresse}</span>
            </div>
            <div class="info-item">
                <span class="label">Contact</span>
                <span class="value">{$adherentNumero} • {$adherentEmail}</span>
            </div>
            <div class="info-item">
                <span class="label">Période</span>
                <span class="value">{$periode}</span>
            </div>
            <div class="info-item">
                <span class="label">Date d'émission</span>
                <span class="value">{$dateCreation}</span>
            </div>
            <div class="info-item">
                <span class="label">Statut</span>
                <span class="value"><span class="badge">Payé</span></span>
            </div>
        </div>

        <div class="montant-box">
            <div class="label">Montant encaissé</div>
            <div class="montant">{$montantFormate}</div>
            <div class="devise">MGA</div>
            <div class="lettres">Soit la somme de {$montantLettres} Ariary</div>
        </div>

        <table class="details-table">
            <tr>
                <td>Référence paiement</td>
                <td>{$reference}</td>
            </tr>
            <tr>
                <td>Mode de paiement</td>
                <td>{$modePaiement}</td>
            </tr>
            <tr>
                <td>Document généré le</td>
                <td>{$dateCreation}</td>
            </tr>
        </table>

        <div class="footer">
            <div class="mention">
                <strong>SEBTP</strong><br>
                Ce document fait foi pour la comptabilité
            </div>
            <div class="signature">
                <div class="line"></div>
                Cachet et signature du receveur
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Rendu HTML de la note de débit
     */
    private function renderNoteDebitHtml(Document $document, string $motif): string
    {
        $adherent = $document->getAdherent();
        $numero = $document->getNumero();
        $montantFormate = number_format($document->getMontant(), 0, '.', ' ');
        $montantLettres = $this->nombreEnLettres($document->getMontant());
        $dateCreation = $document->getDateCreation()->format('d/m/Y H:i');
        $dateEcheance = (new \DateTime())->modify('+30 days')->format('d/m/Y');
        
        $adherentNom = $adherent->getNom() ?? 'Non renseigné';
        $adherentAdresse = $adherent->getAdresse() ?? 'Non renseignée';
        $adherentEmail = $adherent->getEmail() ?? 'Non renseigné';
        $adherentNumero = $adherent->getNumero() ?? 'Non renseigné';
        $periode = $document->getPeriode() ?? 'Non définie';
        $reference = $document->getReference() ?? 'N/A';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Note de débit N° {$numero}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #fff; padding: 30px; color: #1a1a2e; }
        .page { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 20px rgba(0,0,0,0.08); padding: 50px 45px; border: 1px solid #e8e8e8; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #c0392b; padding-bottom: 25px; margin-bottom: 30px; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .logo { width: 60px; height: 60px; background: #c0392b; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 900; }
        .header-left h1 { font-size: 22px; font-weight: 800; color: #c0392b; letter-spacing: 1px; }
        .header-left p { font-size: 12px; color: #666; margin-top: 2px; }
        .header-right { text-align: right; }
        .header-right .title { font-size: 22px; font-weight: 800; color: #c0392b; letter-spacing: 2px; }
        .header-right .subtitle { font-size: 12px; color: #666; margin-top: 4px; }
        .header-right .num { font-size: 14px; font-weight: 700; color: #c0392b; margin-top: 6px; }
        .badge { display: inline-block; background: #c0392b; color: #fff; padding: 4px 18px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 25px 0 30px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .info-item { display: flex; flex-direction: column; }
        .info-item .label { font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; font-weight: 600; }
        .info-item .value { font-size: 14px; font-weight: 600; color: #1a1a2e; margin-top: 3px; }
        .motif-box { padding: 18px 22px; background: #fef5f5; border-left: 4px solid #c0392b; border-radius: 8px; margin: 20px 0 25px 0; }
        .motif-box .label { font-size: 11px; text-transform: uppercase; color: #888; font-weight: 600; letter-spacing: 0.5px; }
        .motif-box .motif { font-size: 15px; font-weight: 500; margin-top: 5px; color: #1a1a2e; }
        .montant-box { text-align: center; padding: 30px; background: linear-gradient(135deg, #fef5f5 0%, #fce8e8 100%); border-radius: 12px; margin: 25px 0 30px 0; border: 2px solid #c0392b; }
        .montant-box .label { font-size: 13px; color: #555; text-transform: uppercase; letter-spacing: 1px; }
        .montant-box .montant { font-size: 38px; font-weight: 900; color: #c0392b; margin: 8px 0 6px 0; }
        .montant-box .devise { font-size: 16px; color: #555; }
        .montant-box .lettres { font-size: 14px; color: #555; font-style: italic; margin-top: 5px; }
        .echeance { text-align: center; padding: 12px; background: #f8f9fa; border-radius: 8px; margin: 20px 0 25px 0; font-size: 14px; }
        .echeance strong { color: #c0392b; }
        .details-table { width: 100%; margin: 20px 0 25px 0; border-collapse: collapse; }
        .details-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .details-table td:first-child { font-weight: 600; color: #555; width: 35%; }
        .details-table td:last-child { font-weight: 500; }
        .footer { margin-top: 35px; padding-top: 20px; border-top: 2px solid #eee; display: flex; justify-content: space-between; align-items: end; }
        .footer .mention { font-size: 11px; color: #999; }
        .footer .signature { text-align: center; font-size: 11px; color: #666; }
        .footer .signature .line { width: 150px; border-top: 1px solid #333; margin: 25px auto 6px auto; }
        @media print { body { padding: 0; } .page { box-shadow: none; border: none; padding: 40px; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-left">
                <div class="logo">S</div>
                <div>
                    <h1>SEBTP</h1>
                    <p>Syndicat des Entreprises du BTP</p>
                </div>
            </div>
            <div class="header-right">
                <div class="title">NOTE DE DÉBIT</div>
                <div class="subtitle">À régler</div>
                <div class="num">N° {$numero}</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="label">Adhérent</span>
                <span class="value">{$adherentNom}</span>
            </div>
            <div class="info-item">
                <span class="label">Adresse</span>
                <span class="value">{$adherentAdresse}</span>
            </div>
            <div class="info-item">
                <span class="label">Contact</span>
                <span class="value">{$adherentNumero} • {$adherentEmail}</span>
            </div>
            <div class="info-item">
                <span class="label">Période</span>
                <span class="value">{$periode}</span>
            </div>
            <div class="info-item">
                <span class="label">Date d'émission</span>
                <span class="value">{$dateCreation}</span>
            </div>
            <div class="info-item">
                <span class="label">Statut</span>
                <span class="value"><span class="badge">À régler</span></span>
            </div>
        </div>

        <div class="motif-box">
            <div class="label">Motif de la facturation</div>
            <div class="motif">{$motif}</div>
        </div>

        <div class="montant-box">
            <div class="label">Montant à régler</div>
            <div class="montant">{$montantFormate}</div>
            <div class="devise">MGA</div>
            <div class="lettres">Soit la somme de {$montantLettres} Ariary</div>
        </div>

        <div class="echeance">
            <strong>Date d'échéance :</strong> {$dateEcheance} (30 jours à compter de la date d'émission)
        </div>

        <table class="details-table">
            <tr>
                <td>Référence</td>
                <td>{$reference}</td>
            </tr>
            <tr>
                <td>Mode de règlement</td>
                <td>Virement bancaire / Chèque</td>
            </tr>
            <tr>
                <td>Document généré le</td>
                <td>{$dateCreation}</td>
            </tr>
        </table>

        <div class="footer">
            <div class="mention">
                <strong>SEBTP</strong><br>
                Merci de régler sous 30 jours
            </div>
            <div class="signature">
                <div class="line"></div>
                Cachet et signature du créancier
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Convertit un nombre en lettres
     */
    private function nombreEnLettres(float $nombre): string
    {
        $nombre = round($nombre);
        
        $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $dizaines = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];

        if ($nombre < 20) {
            return $unites[$nombre];
        }

        if ($nombre < 100) {
            $dizaine = (int) floor($nombre / 10);
            $reste = $nombre % 10;
            if ($reste === 0) {
                return $dizaines[$dizaine];
            }
            return $dizaines[$dizaine] . '-' . $unites[$reste];
        }

        if ($nombre < 1000) {
            $centaine = (int) floor($nombre / 100);
            $reste = $nombre % 100;
            if ($reste === 0) {
                return ($centaine === 1 ? 'cent' : $unites[$centaine] . ' cents');
            }
            return ($centaine === 1 ? 'cent' : $unites[$centaine] . ' cent') . ' ' . $this->nombreEnLettres($reste);
        }

        if ($nombre < 1000000) {
            $millier = (int) floor($nombre / 1000);
            $reste = $nombre % 1000;
            if ($reste === 0) {
                return ($millier === 1 ? 'mille' : $this->nombreEnLettres($millier) . ' mille');
            }
            return ($millier === 1 ? 'mille' : $this->nombreEnLettres($millier) . ' mille') . ' ' . $this->nombreEnLettres($reste);
        }

        return 'Nombre trop grand';
    }

    public function getDocumentContent(Document $document): string
    {
        if ($document->getContenu()) {
            return $document->getContenu();
        }

        if ($document->getType() === 'recu') {
            $cotisations = $document->getAdherent()->getCotisations();
            $cotisation = $cotisations->last();
            if ($cotisation) {
                return $this->renderRecuHtml($document, $cotisation);
            }
            return '';
        }

        return $this->renderNoteDebitHtml($document, $document->getReference() ?? '');
    }
}