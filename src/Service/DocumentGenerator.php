<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Liste;
use App\Entity\Cotisation;
use App\Entity\Paiement;
use App\Entity\Participation;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentGenerator
{
    // Informations statiques du syndicat (colonne de gauche sur les documents)
    private const SYNDICAT_NOM     = 'SEBTP';
    private const SYNDICAT_SOUS_TITRE = 'Syndicat des Entrepreneurs du Bâtiment et des Travaux Publics';
    private const SYNDICAT_ADRESSE = 'Lot I A 58 Ampatsakana Antananarivo 101';
    private const SYNDICAT_TEL     = '+261 32 05 673 97';
    private const SYNDICAT_EMAIL   = 'syndicatbtp@gmail.com';
    private const SYNDICAT_NIF     = '6003365983';
    private const SYNDICAT_STAT    = '94203 11 2014 0 04230';
    private const SYNDICAT_RIB     = '00008 00005 21000096624 42';

    // Signataire
    private const PRESIDENT_NOM   = 'Hary ANDRIANTEFIHASINA';
    private const PRESIDENT_TITRE = 'Le Président du SEBTP';

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
     * Génère un reçu de paiement pour un paiement spécifique
     */
    public function generateRecu(Paiement $paiement): Document
    {
        $cotisation = $paiement->getCotisation();
        $adherent = $cotisation->getAdherent();
        [$numero, $compteur, $year] = $this->generateNumeroAndCompteur('RECU', $adherent);

        $document = new Document();
        $document->setType('recu');
        $document->setNumero($numero);
        $document->setAdherent($adherent);
        $document->setMontant($paiement->getMontant());
        $document->setPeriode($paiement->getPeriode());
        $document->setReference($paiement->getReference() ?? 'N/A');

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $html = $this->renderRecuHtml($document, $paiement);
        $pdfContent = $this->generatePdf($html);

        $fileName = $this->buildFileName($adherent, 'RECU', $year, $compteur);
        $path = $this->saveDocument($pdfContent, $adherent, $fileName);

        $document->setCheminFichier($path);
        $document->setNomFichier($fileName);
        $document->setContenu($html);

        $this->entityManager->flush();

        return $document;
    }

    /**
     * Génère un reçu à partir d'une participation (pour les événements)
     */
    public function generateRecuFromParticipation(Participation $participation): Document
    {
        $adherent = $participation->getAdherent();
        $evenement = $participation->getEvenement();
        [$numero, $compteur, $year] = $this->generateNumeroAndCompteur('RECU', $adherent);

        $document = new Document();
        $document->setType('recu');
        $document->setNumero($numero);
        $document->setAdherent($adherent);
        $document->setMontant($participation->getMontantTotal());
        $document->setReference('Evenement: ' . $evenement->getNom());

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $html = $this->renderRecuFromParticipationHtml($document, $participation);
        $pdfContent = $this->generatePdf($html);

        $fileName = $this->buildFileName($adherent, 'RECU', $year, $compteur);
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
        [$numero, $compteur, $year] = $this->generateNumeroAndCompteur('note_debit', $adherent);

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

        $fileName = $this->buildFileName($adherent, 'NDD', $year, $compteur);
        $path = $this->saveDocument($pdfContent, $adherent, $fileName);

        $document->setCheminFichier($path);
        $document->setNomFichier($fileName);
        $document->setContenu($html);

        $this->entityManager->flush();

        return $document;
    }

    /**
     * Génère le numéro de document ainsi que le compteur brut (utilisé aussi pour le nom de fichier)
     * Le compteur est spécifique à l'adhérent pour le nom de fichier
     *
     * @return array{0: string, 1: string, 2: string} [numero, compteur (4 chiffres), année]
     */
    /**
 * Génère le numéro de document ainsi que le compteur brut
 */
    /**
 * Génère le numéro de document ainsi que le compteur brut (utilisé aussi pour le nom de fichier)
 * Le compteur est spécifique à l'adhérent pour le nom de fichier
 *
 * @return array{0: string, 1: string, 2: string} [numero, compteur (4 chiffres), année]
 */
    private function generateNumeroAndCompteur(string $type, Liste $adherent): array
{
    $year = date('Y');
    $adherentId = $adherent->getId();

    // Correspondance entre type en base et préfixe affiché
    $prefixe = match ($type) {
        'note_debit' => 'NDD',
        'recu' => 'RECU',
        default => strtoupper($type),
    };

    $conn = $this->entityManager->getConnection();

    // ===== Compteur global =====
    $sqlGlobal = "
        SELECT COUNT(*)
        FROM document
        WHERE type = :type
        AND YEAR(date_creation) = :year
    ";

    $stmtGlobal = $conn->prepare($sqlGlobal);
    $stmtGlobal->bindValue('type', $type);
    $stmtGlobal->bindValue('year', $year);

    $countGlobal = (int) $stmtGlobal->executeQuery()->fetchOne() + 1;

    $compteurGlobal = str_pad(
        (string) $countGlobal,
        4,
        '0',
        STR_PAD_LEFT
    );

    // Numéro affiché dans le document
    $numero = sprintf(
        '%s-%s-%s',
        $prefixe,
        $year,
        $compteurGlobal
    );

    // ===== Compteur fichier par adhérent =====
    $sqlAdherent = "
        SELECT COUNT(*)
        FROM document
        WHERE adherent_id = :adherentId
        AND type = :type
        AND YEAR(date_creation) = :year
    ";

    $stmtAdherent = $conn->prepare($sqlAdherent);
    $stmtAdherent->bindValue('adherentId', $adherentId);
    $stmtAdherent->bindValue('type', $type);
    $stmtAdherent->bindValue('year', $year);

    $countAdherent = (int) $stmtAdherent->executeQuery()->fetchOne() + 1;

    $compteurFichier = str_pad(
        (string) $countAdherent,
        4,
        '0',
        STR_PAD_LEFT
    );

    return [$numero, $compteurFichier, $year];
}

    /**
     * Nettoie une chaîne pour l'utiliser dans un nom de fichier
     * (retire les accents/caractères spéciaux, remplace les espaces par des underscores)
     */
    private function slugifyForFileName(string $value): string
    {
        $value = trim($value);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value !== '' ? strtoupper($value) : 'ADHERENT';
    }

    /**
     * Construit le nom du fichier téléchargé au format : {NomAdherent}_{PREFIXE}_{Annee}_{Compteur}.pdf
     * Le compteur est spécifique à l'adhérent et s'auto-incrémente.
     */
    private function buildFileName(Liste $adherent, string $prefixe, string $year, string $compteur): string
    {
        $nomSlug = $this->slugifyForFileName($adherent->getNom() ?? 'ADHERENT');

        return sprintf('%s_%s_%s_%s.pdf', $nomSlug, $prefixe, $year, $compteur);
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
     * Récupère une image du dossier public/uploads/images en base64.
     * Essaie plusieurs extensions courantes pour éviter les erreurs de chemin.
     */
    private function getImageBase64(string $baseName): string
    {
        $extensions = ['jpeg', 'jpg', 'png'];
        $mimeMap = [
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'png'  => 'image/png',
        ];

        foreach ($extensions as $ext) {
            $path = $this->projectDir . '/public/images/' . $baseName . '.' . $ext;
            if (file_exists($path)) {
                $data = file_get_contents($path);
                return 'data:' . $mimeMap[$ext] . ';base64,' . base64_encode($data);
            }
        }

        return '';
    }

    /**
     * Récupère le logo en base64 (public/uploads/images/logo_sebtp.jpeg)
     */
    private function getLogoBase64(): string
    {
        return $this->getImageBase64('logo_sebtp');
    }

    /**
     * Récupère la signature/cachet du président en base64, si disponible
     * (public/uploads/images/signature_president.*)
     */
    private function getSignatureBase64(): string
    {
        return $this->getImageBase64('signature_president');
    }

    /**
     * Construit le bloc HTML du logo (image si présente, sinon un bloc de secours)
     */
    private function buildLogoHtml(): string
    {
        $logoBase64 = $this->getLogoBase64();

        if ($logoBase64) {
            return '<img src="' . $logoBase64 . '" alt="SEBTP Logo" style="width:110px;height:auto;object-fit:contain;">';
        }

        return '<div style="width:90px;height:90px;background:#1a1a1a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;">SEBTP</div>';
    }

    /**
     * Construit le bloc HTML de la signature (image si présente, sinon un espace vide)
     */
    private function buildSignatureHtml(): string
    {
        $signatureBase64 = $this->getSignatureBase64();

        if ($signatureBase64) {
            return '<img src="' . $signatureBase64 . '" alt="Signature" style="width:130px;height:auto;object-fit:contain;margin-top:8px;">';
        }

        return '<div style="height:70px;"></div>';
    }

    /**
     * Gabarit HTML commun aux reçus et notes de débit
     *
     * @param array<int, array{designation:string, quantite:string, pu:string, montant:string}> $lignes
     */
    private function renderDocumentHtml(
        string $titrePage,
        string $titreDocument,
        string $numero,
        string $dateCreation,
        string $clientNom,
        string $clientTel,
        string $clientAdresse,
        string $clientNif,
        string $clientStat,
        array $lignes,
        string $totalFormate,
        string $montantLettres,
        string $blocPaiementGaucheHtml
    ): string {
        $logoHtml = $this->buildLogoHtml();
        $signatureHtml = $this->buildSignatureHtml();

        $lignesHtml = '';
        foreach ($lignes as $ligne) {
            $lignesHtml .= '<tr>'
                . '<td style="text-align:left;">' . htmlspecialchars($ligne['designation']) . '</td>'
                . '<td>' . htmlspecialchars($ligne['quantite']) . '</td>'
                . '<td>' . htmlspecialchars($ligne['pu']) . '</td>'
                . '<td>' . htmlspecialchars($ligne['montant']) . '</td>'
                . '</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$titrePage} N° {$numero}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 13px;
            color: #000;
            background: #fff;
            padding: 35px 40px;
        }
        .page { max-width: 760px; margin: 0 auto; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; border: none; padding: 0; }
        .logo-cell { width: 130px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { vertical-align: top; border: none; padding: 0; }
        .info-left { width: 55%; font-size: 13px; line-height: 1.9; }
        .info-right { width: 45%; font-size: 13px; line-height: 1.7; text-align: right; }

        .doit-label {
            font-weight: 700;
            text-decoration: underline;
            font-size: 15px;
            margin: 6px 0 6px 0;
        }
        .client-nom { font-weight: 700; font-size: 15px; margin-bottom: 3px; }
        .client-info { font-size: 13px; margin-top: 2px; }

        .doc-title-box {
            border: 1px solid #000;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            padding: 10px;
            margin: 22px 0 22px 0;
            letter-spacing: 0.5px;
        }

        table.details { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.details th, table.details td {
            border: 1px solid #000;
            padding: 10px;
            font-size: 13px;
            text-align: center;
        }
        table.details th { font-weight: 700; }
        table.details td:first-child, table.details th:first-child { text-align: left; }
        table.details tfoot td { font-weight: 700; }
        table.details tfoot td.total-label { text-align: right; }

        .montant-lettres { margin: 22px 0 26px 0; font-size: 14px; }
        .montant-lettres strong { font-weight: 700; }

        .footer-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .footer-table td { vertical-align: top; border: none; padding: 0; font-size: 13px; }
        .footer-left { width: 58%; line-height: 1.6; }
        .footer-right { width: 42%; text-align: center; line-height: 1.4; padding-top: 15px; }
        .footer-left u { text-decoration: underline; }
        .footer-right .president-titre { font-size: 13px; }
        .footer-right .president-nom { font-weight: 700; font-size: 13px; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="page">

        <table class="header-table">
            <tr>
                <td class="logo-cell">{$logoHtml}</td>
                <td></td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td class="info-left">
                    <div><strong>{$this->e(self::SYNDICAT_ADRESSE)}</strong></div>
                    <div>Tel : {$this->e(self::SYNDICAT_TEL)}</div>
                    <div>Email : {$this->e(self::SYNDICAT_EMAIL)}</div>
                    <div style="margin-top:6px;">NIF : {$this->e(self::SYNDICAT_NIF)}</div>
                    <div>STAT: {$this->e(self::SYNDICAT_STAT)}</div>
                </td>
                <td class="info-right">
                    <div>Antananarivo, le {$dateCreation}</div>
                    <div class="doit-label">DOIT :</div>
                    <div class="client-nom">{$this->e($clientNom)}</div>
                    <div class="client-info">Tél : {$this->e($clientTel)}</div>
                    <div class="client-info">Adresse : {$this->e($clientAdresse)}</div>
                    <div class="client-info">NIF : {$this->e($clientNif)}</div>
                    <div class="client-info">STAT: {$this->e($clientStat)}</div>
                </td>
            </tr>
        </table>

        <div class="doc-title-box">{$this->e($titreDocument)} N°{$this->e($numero)}</div>

        <table class="details">
            <thead>
                <tr>
                    <th>DESIGNATION</th>
                    <th>QUANTITE</th>
                    <th>PU</th>
                    <th>MONTANT</th>
                </tr>
            </thead>
            <tbody>
                {$lignesHtml}
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="total-label">TOTAL</td>
                    <td>{$totalFormate}</td>
                </tr>
            </tfoot>
        </table>

        <div class="montant-lettres">
            Arrêtée à la somme de <strong>{$this->e($montantLettres)}</strong>
        </div>

        <table class="footer-table">
            <tr>
                <td class="footer-left">{$blocPaiementGaucheHtml}</td>
                <td class="footer-right">
                    <div class="president-titre">{$this->e(self::PRESIDENT_TITRE)}</div>
                    {$signatureHtml}
                    <div class="president-nom">{$this->e(self::PRESIDENT_NOM)}</div>
                </td>
            </tr>
        </table>

    </div>
</body>
</html>
HTML;
    }

    /**
     * Échappe une chaîne pour l'insertion dans le HTML
     */
    private function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Bloc "Mode de paiement" statique utilisé sur les notes de débit
     */
    private function buildModePaiementInstructionsHtml(): string
    {
        $rib = $this->e(self::SYNDICAT_RIB);
        $nom = $this->e(self::SYNDICAT_NOM);

        return <<<HTML
<strong><u>Mode de paiement :</u><br>
Par virement bancaire au nom de {$nom}<br>
RIB SG :  {$rib}<br>
<br>
Ou<br>
<br>
Par chèque au nom de « {$nom} »<br>
<br>
A déposer au siège {$nom} Lot IA 58 Ampatsakana <strong>
HTML;
    }

    /**
     * Bloc récapitulatif du paiement effectué, utilisé sur les reçus
     */
    private function buildPaiementEffectueHtml(string $modePaiement, string $reference, string $datePaiement): string
    {
        return <<<HTML
<u>Paiement reçu :</u><br>
Mode de paiement : {$this->e($modePaiement)}<br>
Référence : {$this->e($reference)}<br>
Date de paiement : {$this->e($datePaiement)}
HTML;
    }

    /**
     * Rendu HTML du reçu
     */
    private function renderRecuHtml(Document $document, Paiement $paiement): string
    {
        $adherent = $document->getAdherent();
        $numero = $document->getNumero();
        $montantFormate = number_format($document->getMontant(), 0, '.', ' ');
        $montantLettres = ucfirst($this->nombreEnLettres($document->getMontant())) . ' Ariary';
        $dateCreation = $document->getDateCreation()->format('d F Y');
        $datePaiement = $paiement->getDatePaiement()->format('d/m/Y');

        $adherentNom = $adherent->getNom() ?? 'Non renseigné';
        $adherentAdresse = $adherent->getAdresse() ?? 'Non renseignée';
        $adherentNumero = $adherent->getNumero() ?? 'Non renseigné';
        $adherentNif = $adherent->getNif() ?? 'Non renseigné';
        $adherentStat = $adherent->getStat() ?? 'Non renseigné';
        $periode = $document->getPeriode() ?? 'Non définie';
        $reference = $paiement->getReference() ?? 'N/A';
        $modePaiement = $paiement->getModePaiement() ?? 'Non spécifié';

        $lignes = [[
            'designation' => 'Cotisation ' . $periode,
            'quantite' => '1',
            'pu' => $montantFormate,
            'montant' => $montantFormate,
        ]];

        return $this->renderDocumentHtml(
            'Reçu de paiement',
            'REÇU',
            $numero,
            $dateCreation,
            $adherentNom,
            $adherentNumero,
            $adherentAdresse,
            $adherentNif,
            $adherentStat,
            $lignes,
            $montantFormate,
            $montantLettres,
            $this->buildPaiementEffectueHtml($modePaiement, $reference, $datePaiement)
        );
    }

    /**
     * Rendu HTML du reçu pour une participation (événement)
     */
    private function renderRecuFromParticipationHtml(Document $document, Participation $participation): string
    {
        $adherent = $document->getAdherent();
        $evenement = $participation->getEvenement();
        $numero = $document->getNumero();
        $montantFormate = number_format($document->getMontant(), 0, '.', ' ');
        $montantLettres = ucfirst($this->nombreEnLettres($document->getMontant())) . ' Ariary';
        $dateCreation = $document->getDateCreation()->format('d F Y');
        $datePaiement = $participation->getDatePaiement()
            ? $participation->getDatePaiement()->format('d/m/Y')
            : $dateCreation;

        $adherentNom = $adherent->getNom() ?? 'Non renseigné';
        $adherentAdresse = $adherent->getAdresse() ?? 'Non renseignée';
        $adherentNumero = $adherent->getNumero() ?? 'Non renseigné';
        $adherentNif = $adherent->getNif() ?? 'Non renseigné';
        $adherentStat = $adherent->getStat() ?? 'Non renseigné';
        $evenementNom = $evenement->getNom() ?? 'Événement';
        $prixUnit = (string) ($evenement->getMontant() ?? 'Non renseigné');
        $quantite = (string) ($participation->getQuantite() ?? '1');

        $lignes = [[
            'designation' => 'Participation - ' . $evenementNom,
            'quantite' => $quantite,
            'pu' => $prixUnit,
            'montant' => $montantFormate,
        ]];

        return $this->renderDocumentHtml(
            'Reçu de paiement',
            'REÇU',
            $numero,
            $dateCreation,
            $adherentNom,
            $adherentNumero,
            $adherentAdresse,
            $adherentNif,
            $adherentStat,
            $lignes,
            $montantFormate,
            $montantLettres,
            $this->buildPaiementEffectueHtml('Non spécifié', 'Evenement: ' . $evenementNom, $datePaiement)
        );
    }

    /**
     * Rendu HTML de la note de débit
     */
    private function renderNoteDebitHtml(Document $document, string $motif): string
    {
        $adherent = $document->getAdherent();
        $numero = $document->getNumero();
        $montantFormate = number_format($document->getMontant(), 0, '.', ' ');
        $montantLettres = ucfirst($this->nombreEnLettres($document->getMontant())) . ' Ariary';
        $dateCreation = $document->getDateCreation()->format('d F Y');

        $adherentNom = $adherent->getNom() ?? 'Non renseigné';
        $adherentAdresse = $adherent->getAdresse() ?? 'Non renseignée';
        $adherentNumero = $adherent->getNumero() ?? 'Non renseigné';
        $adherentNif = $adherent->getNif() ?? 'Non renseigné';
        $adherentStat = $adherent->getStat() ?? 'Non renseigné';
        $periode = $document->getPeriode() ?? 'Non définie';
        $motif = $document->getReference() ?? 'Non définie';

        $lignes = [[
            'designation' => $motif,
            'quantite' => '1',
            'pu' => $montantFormate,
            'montant' => $montantFormate,
        ]];

        return $this->renderDocumentHtml(
            'Note de débit',
            'NOTE DE DEBIT',
            $numero,
            $dateCreation,
            $adherentNom,
            $adherentNumero,
            $adherentAdresse,
            $adherentNif,
            $adherentStat,
            $lignes,
            $montantFormate,
            $montantLettres,
            $this->buildModePaiementInstructionsHtml()
        );
    }

    /**
     * Version simplifiée pour l'affichage des reçus existants (sans les données du paiement)
     */
    private function renderRecuHtmlSimple(Document $document): string
    {
        $adherent = $document->getAdherent();
        $numero = $document->getNumero();
        $montantFormate = number_format($document->getMontant(), 0, '.', ' ');
        $montantLettres = ucfirst($this->nombreEnLettres($document->getMontant())) . ' Ariary';
        $dateCreation = $document->getDateCreation()->format('d F Y');

        $adherentNom = $adherent->getNom() ?? 'Non renseigné';
        $adherentAdresse = $adherent->getAdresse() ?? 'Non renseignée';
        $adherentNumero = $adherent->getNumero() ?? 'Non renseigné';
        $adherentNif = $adherent->getNif() ?? 'Non renseigné';
        $adherentStat = $adherent->getStat() ?? 'Non renseigné';
        $periode = $document->getPeriode() ?? 'Non définie';
        $reference = $document->getReference() ?? 'N/A';

        $lignes = [[
            'designation' => 'Cotisation ' . $periode,
            'quantite' => '1',
            'pu' => $montantFormate,
            'montant' => $montantFormate,
        ]];

        return $this->renderDocumentHtml(
            'Reçu de paiement',
            'REÇU',
            $numero,
            $dateCreation,
            $adherentNom,
            $adherentNumero,
            $adherentAdresse,
            $adherentNif,
            $adherentStat,
            $lignes,
            $montantFormate,
            $montantLettres,
            $this->buildPaiementEffectueHtml('Non spécifié', $reference, $dateCreation)
        );
    }

    /**
     * Convertit un nombre en lettres (version optimisée sans récursion excessive)
     */
    private function nombreEnLettres(float $nombre): string
    {
        $nombre = round($nombre);

        if ($nombre === 0.0) {
            return 'zéro';
        }

        $parties = [];
        $reste = $nombre;

        // Milliards
        if ($reste >= 1000000000) {
            $milliards = (int) floor($reste / 1000000000);
            $reste = $reste % 1000000000;
            if ($milliards === 1) {
                $parties[] = 'un milliard';
            } else {
                $parties[] = $this->nombreEnLettres($milliards) . ' milliards';
            }
        }

        // Millions
        if ($reste >= 1000000) {
            $millions = (int) floor($reste / 1000000);
            $reste = $reste % 1000000;
            if ($millions === 1) {
                $parties[] = 'un million';
            } else {
                $parties[] = $this->nombreEnLettres($millions) . ' millions';
            }
        }

        // Milliers
        if ($reste >= 1000) {
            $milliers = (int) floor($reste / 1000);
            $reste = $reste % 1000;
            if ($milliers === 1) {
                $parties[] = 'mille';
            } else {
                $parties[] = $this->nombreEnLettres($milliers) . ' mille';
            }
        }

        // Centaines et unités
        if ($reste > 0) {
            $parties[] = $this->nombreEnLettresMoinsDeMille($reste);
        }

        return implode(' ', $parties);
    }

    /**
     * Convertit un nombre inférieur à 1000 en lettres (sans récursion)
     */
    private function nombreEnLettresMoinsDeMille(float $nombre): string
    {
        $nombre = round($nombre);

        $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $dizaines = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];

        if ($nombre < 20) {
            return $unites[(int) $nombre];
        }

        if ($nombre < 100) {
            $dizaine = (int) floor($nombre / 10);
            $reste = (int) ($nombre % 10);
            if ($reste === 0) {
                return $dizaines[$dizaine];
            }
            if ($dizaine === 7) {
                return 'soixante-' . $unites[10 + $reste];
            }
            if ($dizaine === 9) {
                return 'quatre-vingt-' . $unites[10 + $reste];
            }
            return $dizaines[$dizaine] . '-' . $unites[$reste];
        }

        // Nombre entre 100 et 999
        $centaine = (int) floor($nombre / 100);
        $reste = (int) ($nombre % 100);
        if ($reste === 0) {
            return ($centaine === 1 ? 'cent' : $unites[$centaine] . ' cents');
        }
        $result = ($centaine === 1 ? 'cent' : $unites[$centaine] . ' cent');
        return $result . ' ' . $this->nombreEnLettresMoinsDeMille($reste);
    }

    /**
     * Récupère le contenu d'un document
     */
    public function getDocumentContent(Document $document): string
    {
        if ($document->getContenu()) {
            return $document->getContenu();
        }

        if ($document->getType() === 'recu') {
            return $this->renderRecuHtmlSimple($document);
        }

        return $this->renderNoteDebitHtml($document, $document->getReference() ?? '');
    }
}