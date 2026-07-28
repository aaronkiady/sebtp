<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Liste;
use App\Entity\Cotisation;
use App\Entity\Paiement;
use App\Entity\Participation;
use App\Repository\DocumentRepository;
use App\Service\CotisationCalculator;
use App\Service\DocumentGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/documents')]
class DocumentController extends AbstractController
{
    private DocumentGenerator $documentGenerator;

    public function __construct(DocumentGenerator $documentGenerator)
    {
        $this->documentGenerator = $documentGenerator;
    }

    #[Route('/adherent/{id}', name: 'app_document_adherent', methods: ['GET'])]
    public function index(int $id, EntityManagerInterface $em, DocumentRepository $repo): Response
    {
        $adherent = $em->getRepository(Liste::class)->find($id);
        
        if (!$adherent) {
            throw $this->createNotFoundException('Adhérent non trouvé');
        }
        
        $documents = $repo->findDocumentsByAdherent($id);

        return $this->render('document/index.html.twig', [
            'adherent' => $adherent,
            'documents' => $documents,
        ]);
    }

    #[Route('/generer-recu/{cotisation_id}', name: 'app_document_generer_recu', methods: ['GET'])]
    public function genererRecu(int $cotisation_id, EntityManagerInterface $em): Response
    {
        $cotisation = $em->getRepository(Cotisation::class)->find($cotisation_id);
        
        if (!$cotisation) {
            $this->addFlash('error', 'Cotisation non trouvée');
            return $this->redirectToRoute('app_home');
        }
        
        try {
            $document = $this->documentGenerator->generateRecu($cotisation);
            $this->addFlash('success', 'Reçu généré avec succès !');
            
            // Rediriger vers la page des documents de l'adhérent
            return $this->redirectToRoute('app_document_adherent', ['id' => $cotisation->getAdherent()->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du reçu : ' . $e->getMessage());
            return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $cotisation->getAdherent()->getId()]);
        }
    }

    #[Route('/generer-note-debit/{adherent_id}', name: 'app_document_generer_note_debit', methods: ['GET', 'POST'])]
    public function genererNoteDebit(Request $request, int $adherent_id, EntityManagerInterface $em): Response
    {
        $adherent = $em->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            $this->addFlash('error', 'Adhérent non trouvé');
            return $this->redirectToRoute('app_document_adherent', ['id' => $adherent_id]);
        }
        
        if ($request->isMethod('POST')) {
            $montant = $request->request->get('montant');
            $periode = $request->request->get('periode');
            $motif = $request->request->get('motif');

            if (!$montant || !$periode || !$motif) {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_document_generer_note_debit', ['adherent_id' => $adherent->getId()]);
            }

            try {
                $document = $this->documentGenerator->generateNoteDebit($adherent, (float) $montant, $periode, $motif);
                $this->addFlash('success', 'Note de débit générée avec succès !');
                return $this->redirectToRoute('app_document_adherent', ['id' => $adherent->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la génération : ' . $e->getMessage());
            }
        }

        return $this->render('document/note_debit_form.html.twig', [
            'adherent' => $adherent,
        ]);
    }

    #[Route('/generer-cotisation/{adherent_id}', name: 'app_document_generer_cotisation', methods: ['GET', 'POST'])]
    public function genererCotisation(Request $request, int $adherent_id, EntityManagerInterface $em, CotisationCalculator $calculator): Response
    {
        $adherent = $em->getRepository(Liste::class)->find($adherent_id);
        
        if (!$adherent) {
            $this->addFlash('error', 'Adhérent non trouvé');
            return $this->redirectToRoute('app_document_adherent', ['id' => $adherent_id]);
        }
        
        $periode = $request->query->get('periode') ?? date('Y');
        
        if ($request->isMethod('POST')) {
            $periode = $request->request->get('periode', date('Y'));
            
            try {
                // Calculer le montant avec le barème actuel (pas celui stocké)
                $result = $calculator->calculateMontantWithBareme($adherent, $calculator->createDateFromPeriode($periode));
                $montant = $result['montant'];
                
                // Récupérer la cotisation existante ou en créer une nouvelle
                $cotisation = $em->getRepository(Cotisation::class)
                    ->findOneBy([
                        'adherent' => $adherent,
                        'periode' => $periode
                    ]);
                
                if ($cotisation) {
                    // Mettre à jour le montant de la cotisation avec le barème actuel
                    $cotisation->setMontant($montant);
                    $cotisation->setBaremeId($result['baremeId']);
                    $cotisation->setBaremeLibelle($result['baremeLibelle']);
                    $em->flush();
                } else {
                    // Créer une nouvelle cotisation
                    $cotisation = new Cotisation();
                    $cotisation->setAdherent($adherent);
                    $cotisation->setPeriode($periode);
                    $cotisation->setMontant($montant);
                    $cotisation->setMontantPaye(0);
                    $cotisation->setStatut('impaye');
                    $cotisation->setBaremeId($result['baremeId']);
                    $cotisation->setBaremeLibelle($result['baremeLibelle']);
                    $em->persist($cotisation);
                    $em->flush();
                }
                
                // Motif = juste "Cotisation {période}"
                $motif = sprintf('Cotisation %s', $periode);
                
                // Générer la note de débit avec le montant recalculé
                $document = $this->documentGenerator->generateNoteDebit($adherent, $montant, $periode, $motif);
                
                $this->addFlash('success', sprintf('Note de débit pour cotisation %s générée avec succès ! Montant : %s MGA', $periode, number_format($montant, 0, '.', ' ')));
                
                return $this->redirectToRoute('app_document_adherent', ['id' => $adherent->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la génération : ' . $e->getMessage());
                return $this->redirectToRoute('app_document_generer_cotisation', ['adherent_id' => $adherent->getId(), 'periode' => $periode]);
            }
        }
        
        // Calculer le montant pour la période sélectionnée (affichage GET)
        $montantCalcule = 0;
        $baremeLibelle = 'Non défini';
        try {
            $result = $calculator->calculateMontantWithBareme($adherent, $calculator->createDateFromPeriode($periode));
            $montantCalcule = $result['montant'];
            $baremeLibelle = $result['baremeLibelle'] ?? 'Barème ' . $periode;
        } catch (\Exception $e) {
            // Pas de barème trouvé
        }
        
        return $this->render('document/cotisation_form.html.twig', [
            'adherent' => $adherent,
            'periode' => $periode,
            'montantCalcule' => $montantCalcule,
            'baremeLibelle' => $baremeLibelle,
            'nbEmployes' => $adherent->getNbEmployes(),
        ]);
    }

    #[Route('/generer-recu-evenement/{participation_id}', name: 'app_document_generer_recu_evenement', methods: ['GET'])]
    public function genererRecuEvenement(int $participation_id, EntityManagerInterface $em): Response
    {
        $participation = $em->getRepository(Participation::class)->find($participation_id);
        
        if (!$participation) {
            $this->addFlash('error', 'Participation non trouvée');
            return $this->redirectToRoute('app_home');
        }
        
        try {
            // Créer un reçu à partir d'une participation
            $document = $this->documentGenerator->generateRecuFromParticipation($participation);
            $this->addFlash('success', 'Reçu généré avec succès !');
            
            return $this->redirectToRoute('app_document_adherent', ['id' => $participation->getAdherent()->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du reçu : ' . $e->getMessage());
            return $this->redirectToRoute('app_evenement_history', ['adherent_id' => $participation->getAdherent()->getId()]);
        }
    }

    #[Route('/download/{id}', name: 'app_document_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        $path = $document->getCheminFichier();
        
        if (!file_exists($path)) {
            $this->addFlash('error', 'Le fichier n\'existe pas.');
            return $this->redirectToRoute('app_document_adherent', ['id' => $document->getAdherent()->getId()]);
        }

        return $this->file($path, $document->getNomFichier(), ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/delete/{id}', name: 'app_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_document_' . $document->getId(), $request->request->get('_token'))) {
            $path = $document->getCheminFichier();
            if (file_exists($path)) {
                unlink($path);
            }
            $em->remove($document);
            $em->flush();
            $this->addFlash('success', 'Document supprimé avec succès.');
        }

        return $this->redirectToRoute('app_document_adherent', ['id' => $document->getAdherent()->getId()]);
    }

    #[Route('/all-adherents', name: 'app_document_all_adherents', methods: ['GET'])]
    public function allAdherents(EntityManagerInterface $em): Response
    {
        $adherents = $em->getRepository(Liste::class)->findBy([], ['nom' => 'ASC']);
        
        return $this->render('document/all_adherents.html.twig', [
            'adherents' => $adherents,
        ]);
    }

    #[Route('/generer-recu-paiement/{paiement_id}', name: 'app_document_generer_recu_paiement', methods: ['GET'])]
public function genererRecuPaiement(int $paiement_id, EntityManagerInterface $em): Response
{
    $paiement = $em->getRepository(Paiement::class)->find($paiement_id);
    
    if (!$paiement) {
        $this->addFlash('error', 'Paiement non trouvé');
        return $this->redirectToRoute('app_home');
    }
    
    try {
        $document = $this->documentGenerator->generateRecu($paiement);
        $this->addFlash('success', 'Reçu généré avec succès !');
        
        return $this->redirectToRoute('app_document_adherent', ['id' => $paiement->getCotisation()->getAdherent()->getId()]);
    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur lors de la génération du reçu : ' . $e->getMessage());
        return $this->redirectToRoute('app_cotisation_history', ['adherent_id' => $paiement->getCotisation()->getAdherent()->getId()]);
    }
}
}